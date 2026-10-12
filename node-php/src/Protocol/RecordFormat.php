<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Protocol;

use OnionServiceDiscovery\Crypto\CanonicalJson;

final class RecordFormat
{
    public const TYPES = [
        'service_manifest' => ['domain' => 'service-manifest', 'excluded' => ['signature'], 'key' => 'service_identity_public_key', 'bytes' => 16384],
        'service_identity_key_rotation' => ['domain' => 'service-key-rotation', 'excluded' => ['old_key_signature', 'new_key_confirmation'], 'key' => 'old_public_key', 'bytes' => 8192],
        'onion_service_migration' => ['domain' => 'onion-migration', 'excluded' => ['service_identity_signature'], 'key' => 'service_identity_public_key', 'bytes' => 8192],
        'node_descriptor' => ['domain' => 'node-descriptor', 'excluded' => ['signature'], 'key' => 'node_identity_public_key', 'bytes' => 16384],
        'direct_observation' => ['domain' => 'direct-observation', 'excluded' => ['signature'], 'key' => 'node_identity_public_key', 'bytes' => 8192],
        'snapshot_descriptor' => ['domain' => 'snapshot-descriptor', 'excluded' => ['signature'], 'key' => 'node_identity_public_key', 'bytes' => 65536],
    ];

    public static function type(\stdClass $record): string
    {
        if (($record->protocol ?? null) !== 'Onion Service Discovery' || ($record->protocol_version ?? null) !== 1) {
            throw new ProtocolException('PROTOCOL_UNSUPPORTED');
        }
        $type = $record->record_type ?? null;
        if (!is_string($type) || !isset(self::TYPES[$type])) {
            throw new ProtocolException('SCHEMA_INVALID');
        }
        return $type;
    }

    public static function payload(\stdClass $record): \stdClass
    {
        $type = self::type($record);
        $payload = clone $record;
        foreach (self::TYPES[$type]['excluded'] as $field) {
            unset($payload->{$field});
        }
        return $payload;
    }

    public static function message(\stdClass $record): string
    {
        $type = self::type($record);
        return 'Onion Service Discovery/' . self::TYPES[$type]['domain'] . "/v1\0" . CanonicalJson::encode(self::payload($record));
    }

    public static function contentHash(\stdClass $record): string
    {
        $type = self::type($record);
        $domain = $type === 'service_manifest' ? 'service-manifest-hash' : self::TYPES[$type]['domain'] . '-hash';
        return 'sha256:' . hash('sha256', 'Onion Service Discovery/' . $domain . "/v1\0" . CanonicalJson::encode(self::payload($record)));
    }
}
