<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Protocol;

use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Crypto\Encoding;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Validation\StrictJson;
use Opis\JsonSchema\CompliantValidator;

final class RecordVerifier
{
    private readonly CompliantValidator $validator;
    /**
     * @var array<array-key, mixed>
     */
    private array $schemas = [];

    public function __construct(private readonly string $schemaDirectory)
    {
        $this->validator = new CompliantValidator();
    }

    public function verifyWire(string $wire, ?OnionAddress $expectedOnion = null): ValidatedRecord
    {
        $record = StrictJson::parse($wire, 65536);
        $type = RecordFormat::type($record);
        if (strlen($wire) > RecordFormat::TYPES[$type]['bytes']) {
            throw new ProtocolException('DOCUMENT_TOO_LARGE');
        }
        return $this->verify($record, $expectedOnion);
    }

    public function verify(\stdClass $record, ?OnionAddress $expectedOnion = null): ValidatedRecord
    {
        return ValidatedRecord::verify($record, $this, $expectedOnion);
    }

    public function assertValid(\stdClass $record, ?OnionAddress $expectedOnion = null): void
    {
        $this->assertStructure($record, $expectedOnion);
        $type = RecordFormat::type($record);
        $message = RecordFormat::message($record);
        $key = RecordFormat::TYPES[$type]['key'];
        $signature = RecordFormat::TYPES[$type]['excluded'][0];
        if (!$this->signatureValid(Encoding::signature($record->{$signature}), $message, Encoding::publicKey($record->{$key}))) {
            throw new ProtocolException('SIGNATURE_INVALID');
        }
        if ($type === 'service_identity_key_rotation' && !$this->signatureValid(
            Encoding::signature($record->new_key_confirmation), $message, Encoding::publicKey($record->new_public_key)
        )) {
            throw new ProtocolException('KEY_ROTATION_INVALID');
        }
    }

    /** Signing preparation only: does not establish cryptographic authenticity. */
    public function assertStructure(\stdClass $record, ?OnionAddress $expectedOnion = null): void
    {
        $type = RecordFormat::type($record);
        $canonical = CanonicalJson::encode($record);
        if (strlen($canonical) > RecordFormat::TYPES[$type]['bytes']) {
            throw new ProtocolException('DOCUMENT_TOO_LARGE');
        }
        if (!isset($this->schemas[$type])) {
            $bytes = file_get_contents($this->schemaDirectory . '/' . $type . '.json');
            if ($bytes === false) {
                throw new \RuntimeException('Required local schema unavailable');
            }
            $this->schemas[$type] = json_decode($bytes, false, 32, JSON_THROW_ON_ERROR);
        }
        if (!$this->validator->validate($record, $this->schemas[$type])->isValid()) {
            throw new ProtocolException('SCHEMA_INVALID');
        }
        $this->semantics($record);
        if ($expectedOnion !== null && $type === 'service_manifest' && $record->onion !== $expectedOnion->hostname) {
            throw new ProtocolException('ONION_BINDING_MISMATCH');
        }
    }

    /** @param non-empty-string $signature
     * @param non-empty-string $publicKey
     */
    private function signatureValid(string $signature, string $message, string $publicKey): bool
    {
        try { sodium_crypto_sign_ed25519_pk_to_curve25519(substr($signature, 0, 32)); }
        catch (\SodiumException) { return false; }
        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    private function semantics(\stdClass $record): void
    {
        foreach (['onion', 'node_onion', 'from_onion', 'to_onion'] as $field) {
            if (isset($record->{$field})) {
                OnionAddress::parse($record->{$field});
            }
        }
        foreach (['service_identity_public_key', 'node_identity_public_key', 'old_public_key', 'new_public_key'] as $field) {
            if (isset($record->{$field})) {
                Encoding::publicKey($record->{$field});
            }
        }
        foreach (RecordFormat::TYPES[$record->record_type]['excluded'] as $field) {
            Encoding::signature($record->{$field});
        }
        foreach (['published_at', 'observed_at', 'created_at'] as $field) {
            if (isset($record->{$field})) {
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $record->{$field}, new \DateTimeZone('UTC'));
                if ($parsed === false || $parsed->format('Y-m-d\TH:i:s\Z') !== $record->{$field}) {
                    throw new ProtocolException('SCHEMA_INVALID');
                }
            }
        }
        $sets = [];
        if (isset($record->service)) {
            foreach ($record->service->languages as $language) { \OnionServiceDiscovery\Domain\LanguageTag::parse($language); }
            $sets[] = $record->service->languages;
            $sets[] = $record->service->categories;
        }
        if (isset($record->links->related_onion_services)) {
            $sets[] = $record->links->related_onion_services;
            foreach ($record->links->related_onion_services as $onion) {
                OnionAddress::parse($onion);
            }
        }
        foreach (['capabilities', 'supported_protocol_versions'] as $field) {
            if (isset($record->{$field})) {
                $sets[] = $record->{$field};
            }
        }
        foreach ($sets as $set) {
            $sorted = $set;
            sort($sorted, is_int($set[0] ?? null) ? SORT_NUMERIC : SORT_STRING);
            if ($set !== $sorted || count(array_unique($set, SORT_REGULAR)) !== count($set)) {
                throw new ProtocolException('SCHEMA_INVALID');
            }
        }
        if ($record->record_type === 'snapshot_descriptor') {
            $count = 0; $seen = [];
            foreach ($record->chunks as $chunk) {
                if (isset($seen[$chunk->hash])) { throw new ProtocolException('SCHEMA_INVALID'); }
                $seen[$chunk->hash] = true; $count += $chunk->record_count;
            }
            if ($count !== $record->record_count || SnapshotFormat::rootHash($record->chunks) !== $record->root_hash) { throw new ProtocolException('SCHEMA_INVALID'); }
        }
        if ($record->record_type === 'service_identity_key_rotation' && (
            $record->old_public_key === $record->new_public_key || $record->effective_revision < 2
        )) {
            throw new ProtocolException('KEY_ROTATION_INVALID');
        }
        if ($record->record_type === 'onion_service_migration' && (
            $record->from_onion === $record->to_onion || $record->effective_revision < 2
        )) {
            throw new ProtocolException('MIGRATION_INVALID');
        }
    }
}
