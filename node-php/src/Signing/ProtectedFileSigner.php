<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Signing;
use OnionServiceDiscovery\Crypto\Encoding;
use OnionServiceDiscovery\Protocol\RecordFormat;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\StorageJson;

/** Encrypted offline key envelope; no generic arbitrary-message signing API. */
final class ProtectedFileSigner implements SignerProvider
{
    private ?string $secretKey;
    private function __construct(private readonly string $keyRole, private readonly string $keyPublic, string $secretKey, private readonly RecordVerifier $verifier)
    { $this->secretKey = $secretKey; }
    public function __destruct() { if ($this->secretKey !== null) { sodium_memzero($this->secretKey); } }
    public function role(): string { return $this->keyRole; }
    public function publicKey(): string { return $this->keyPublic; }

    public static function create(string $path, string $role, string $passphrase, RecordVerifier $verifier): self
    {
        self::checkRole($role);
        self::keyPath($path);
        if (strlen($passphrase) < 12 || strlen($passphrase) > 1024) { throw new SignerException('PASSPHRASE_POLICY'); }
        $pair = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($pair);
        $public = Encoding::encode(sodium_crypto_sign_publickey($pair), 'ed25519:');
        sodium_memzero($pair);
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $metadata = ['version' => 1, 'role' => $role, 'public_key' => $public, 'created_at' => gmdate('Y-m-d\TH:i:s\Z'), 'kdf' => 'argon2id13', 'ops' => 3, 'memory' => 67108864, 'aead' => 'xchacha20poly1305-ietf', 'salt' => base64_encode($salt), 'nonce' => base64_encode($nonce)];
        $key = sodium_crypto_pwhash(32, $passphrase, $salt, 3, 67108864, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($secret, StorageJson::encode($metadata), $nonce, $key);
        sodium_memzero($key);
        $envelope = $metadata + ['ciphertext' => base64_encode($ciphertext)];
        $parent = dirname($path);
        if (str_contains($path, '://') || is_link($parent) || !is_dir($parent) || (fileperms($parent) & 0077) !== 0) { sodium_memzero($secret); throw new SignerException('KEY_DIRECTORY_UNSAFE'); }
        $previousMask = umask(0077);
        try { $stream = @fopen($path, 'x+b'); } finally { umask($previousMask); }
        if ($stream === false) { sodium_memzero($secret); throw new SignerException('KEY_FILE_UNAVAILABLE'); }
        try {
            $bytes = StorageJson::encode($envelope);
            if (!chmod($path, 0600) || fwrite($stream, $bytes) !== strlen($bytes) || !fflush($stream) || !fsync($stream)) { throw new SignerException('KEY_FILE_UNAVAILABLE'); }
            $directory = fopen($parent, 'r'); if ($directory === false) { throw new SignerException('KEY_FILE_UNAVAILABLE'); }
            try { if (!fsync($directory)) { throw new SignerException('KEY_FILE_UNAVAILABLE'); } } finally { fclose($directory); }
        } catch (\Throwable $error) { sodium_memzero($secret); @unlink($path); throw $error; }
        finally { fclose($stream); }
        return new self($role, $public, $secret, $verifier);
    }

    public static function open(string $path, string $expectedRole, string $passphrase, RecordVerifier $verifier): self
    {
        self::checkRole($expectedRole);
        self::keyPath($path);
        if (strlen($passphrase) > 1024 || is_link($path) || !is_file($path) || (fileperms($path) & 0077) !== 0 || filesize($path) > 4096) { throw new SignerException('KEY_FILE_UNAVAILABLE'); }
        try {
            $envelope = self::inspectEnvelope($path, $expectedRole);
            $public = Encoding::publicKey($envelope['public_key']);
            $salt = self::base64($envelope['salt'], 16); $nonce = self::base64($envelope['nonce'], 24); $ciphertext = self::base64($envelope['ciphertext'], 80);
            unset($envelope['ciphertext']);
            $key = sodium_crypto_pwhash(32, $passphrase, $salt, 3, 67108864, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
            $secret = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, StorageJson::encode($envelope), $nonce, $key);
            sodium_memzero($key);
            if ($secret === false || $secret === '') { throw new SignerException('KEY_UNLOCK_FAILED'); }
            $derivedPair = sodium_crypto_sign_seed_keypair(substr($secret, 0, 32));
            $derivedPublic = sodium_crypto_sign_publickey($derivedPair);
            sodium_memzero($derivedPair);
            if (!hash_equals($public, $derivedPublic) || !hash_equals($public, sodium_crypto_sign_publickey_from_secretkey($secret))) { sodium_memzero($secret); throw new SignerException('KEY_ENVELOPE_INVALID'); }
            return new self($expectedRole, $envelope['public_key'], $secret, $verifier);
        } catch (SignerException $error) { throw $error; }
        catch (\Throwable) { throw new SignerException('KEY_ENVELOPE_INVALID'); }
    }

    /** Unauthenticated envelope header for diagnostics; no secret decryption or authentication claim.
     * @return array<array-key,mixed>
     */
    public static function inspectEnvelope(string $path, string $expectedRole): array
    {
        self::checkRole($expectedRole); self::keyPath($path);
        if (is_link($path) || !is_file($path) || (fileperms($path) & 0077) !== 0 || filesize($path) > 4096) { throw new SignerException('KEY_FILE_UNAVAILABLE'); }
        $bytes = file_get_contents($path); if ($bytes === false) { throw new SignerException('KEY_FILE_UNAVAILABLE'); }
        try {
            $envelope = StorageJson::decode($bytes, 4096);
            if (count($envelope) !== 11 || array_diff(array_keys($envelope), ['version','role','public_key','created_at','kdf','ops','memory','aead','salt','nonce','ciphertext']) !== [] ||
                $envelope['version'] !== 1 || $envelope['role'] !== $expectedRole || $envelope['kdf'] !== 'argon2id13' || $envelope['ops'] !== 3 || $envelope['memory'] !== 67108864 || $envelope['aead'] !== 'xchacha20poly1305-ietf' || !is_string($envelope['created_at']) || preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/D', $envelope['created_at']) !== 1) { throw new SignerException('KEY_ENVELOPE_INVALID'); }
            $created = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $envelope['created_at'], new \DateTimeZone('UTC'));
            if ($created === false || $created->format('Y-m-d\TH:i:s\Z') !== $envelope['created_at']) { throw new SignerException('KEY_ENVELOPE_INVALID'); }
            Encoding::publicKey($envelope['public_key']); self::base64($envelope['salt'], 16); self::base64($envelope['nonce'], 24); self::base64($envelope['ciphertext'], 80);
            return $envelope;
        } catch (SignerException $error) { throw $error; }
        catch (\Throwable) { throw new SignerException('KEY_ENVELOPE_INVALID'); }
    }

    public function signRecord(\stdClass $record, bool $rotationConfirmation = false): \stdClass
    {
        if ($this->secretKey === null || $this->secretKey === '') { throw new SignerException('KEY_UNAVAILABLE'); }
        $copy = json_decode(json_encode($record, JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR);
        $type = RecordFormat::type($copy);
        $allow = $this->keyRole === 'service' ? ['service_manifest', 'service_identity_key_rotation', 'onion_service_migration'] : ['node_descriptor', 'direct_observation', 'snapshot_descriptor'];
        if (!in_array($type, $allow, true) || ($rotationConfirmation && $type !== 'service_identity_key_rotation')) { throw new SignerException('SIGNER_ROLE_MISMATCH'); }
        $field = $rotationConfirmation ? 'new_key_confirmation' : RecordFormat::TYPES[$type]['excluded'][0];
        $keyField = $rotationConfirmation ? 'new_public_key' : RecordFormat::TYPES[$type]['key'];
        if (($copy->{$keyField} ?? null) !== $this->keyPublic) { throw new SignerException('SIGNER_KEY_MISMATCH'); }
        // All signature slots must be present with canonical encoding before shape validation.
        foreach (RecordFormat::TYPES[$type]['excluded'] as $slot) {
            if (!isset($copy->{$slot})) { $copy->{$slot} = Encoding::encode(str_repeat("\0", 64), 'ed25519sig:'); }
        }
        $this->verifier->assertStructure($copy);
        $copy->{$field} = Encoding::encode(sodium_crypto_sign_detached(RecordFormat::message($copy), $this->secretKey), 'ed25519sig:');
        return $copy;
    }
    private static function keyPath(string $path): void { if (str_contains($path, '://') || in_array(basename($path), ['hs_ed25519_secret_key', 'private_key'], true)) { throw new SignerException('TOR_KEY_ACCESS_FORBIDDEN'); } }
    private static function checkRole(string $role): void { if (!in_array($role, ['service', 'node'], true)) { throw new SignerException('SIGNER_ROLE_INVALID'); } }
    private static function base64(mixed $value, int $length): string
    {
        if (!is_string($value)) { throw new SignerException('KEY_ENVELOPE_INVALID'); }
        $decoded = base64_decode($value, true);
        if ($decoded === false || strlen($decoded) !== $length || base64_encode($decoded) !== $value) { throw new SignerException('KEY_ENVELOPE_INVALID'); }
        return $decoded;
    }
}
