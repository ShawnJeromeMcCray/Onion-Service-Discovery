<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Signing;

use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\ServiceHistory;
use OnionServiceDiscovery\Protocol\ValidatedRecord;
use OnionServiceDiscovery\Validation\StrictJson;

/** Maintains the public PCP-004 history bundle beside the installed Manifest; public records only. */
final readonly class HistoryInstaller
{
    public const FILE = 'onion-service-discovery-history.json';
    private const TYPES = ['service_manifest', 'service_identity_key_rotation', 'onion_service_migration'];

    public function __construct(private RecordVerifier $verifier) {}

    /**
     * Writes the bundle only when the installed Manifest is the valid current state of one identity.
     * The caller holds the publisher lock.
     * @param list<ValidatedRecord> $extra
     * @return array{state:string,record_count:int}
     */
    public function update(string $wellKnownDirectory, ValidatedRecord $installed, array $extra): array
    {
        $path = $wellKnownDirectory . '/' . self::FILE;
        $candidates = [];
        foreach (array_merge($this->load($path), $extra, [$installed]) as $record) {
            if (in_array($record->type, self::TYPES, true)) { $candidates[$record->contentHash] = $record; }
        }
        $connected = self::connected($installed, $candidates);
        if ($connected === null) { return ['state' => 'INCOMPLETE', 'record_count' => 0]; }
        if (count($connected) > ServiceHistory::MAX_RECORDS) { return ['state' => 'TOO_LARGE', 'record_count' => count($connected)]; }
        $documents = array_map(static fn (ValidatedRecord $record): \stdClass => $record->document(), $connected);
        $wire = json_encode(['protocol' => 'Onion Service Discovery', 'protocol_version' => 1, 'bundle_type' => 'service_history', 'records' => $documents],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        if (strlen($wire) > ServiceHistory::MAX_BYTES) { return ['state' => 'TOO_LARGE', 'record_count' => count($connected)]; }
        if (is_file($path) && file_get_contents($path) === $wire) { return ['state' => 'CONNECTED', 'record_count' => count($connected)]; }
        self::write($wellKnownDirectory, $path, $wire);
        return ['state' => 'CONNECTED', 'record_count' => count($connected)];
    }

    /**
     * Rebuilds the bundle of an existing installation from the owner's signed files.
     * @param list<ValidatedRecord> $records
     * @return array{state:string,record_count:int}
     */
    public function rebuild(string $webroot, array $records): array
    {
        $directory = $webroot . '/.well-known';
        if (str_contains($webroot, '://') || is_link($webroot) || is_link($directory) || !is_dir($directory)) { throw new ProtocolException('WEBROOT_INVALID'); }
        $lockPath = $directory . '/.publisher.lock';
        if (is_link($lockPath)) { throw new ProtocolException('PUBLIC_MANIFEST_PATH_UNSAFE'); }
        $mask = umask(0077); try { $lock = fopen($lockPath, 'c+b'); } finally { umask($mask); }
        if ($lock === false) { throw new ProtocolException('PUBLIC_MANIFEST_WRITE_FAILED'); }
        $deadline = hrtime(true) + 2000000000;
        while (!flock($lock, LOCK_EX | LOCK_NB)) { if (hrtime(true) >= $deadline) { fclose($lock); throw new ProtocolException('PUBLISHER_BUSY'); } usleep(10000); }
        try {
            $installed = $this->installed($directory);
            $result = $this->update($directory, $installed, $records);
            if ($result['state'] !== 'CONNECTED') { throw new ProtocolException($result['state'] === 'TOO_LARGE' ? 'HISTORY_BUNDLE_INVALID' : 'HISTORY_INCOMPLETE'); }
            return $result;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    /**
     * Offline check of what a Node would conclude from the served files (no network).
     * @return array{state:string,record_count:int,manifest_hash:string}
     */
    public function check(string $webroot): array
    {
        $directory = $webroot . '/.well-known';
        $installed = $this->installed($directory);
        $path = $directory . '/' . self::FILE;
        if (is_link($path) || !is_file($path)) { return ['state' => 'MISSING', 'record_count' => 0, 'manifest_hash' => $installed->contentHash]; }
        $bytes = file_get_contents($path, false, null, 0, ServiceHistory::MAX_BYTES + 1);
        if ($bytes === false) { throw new ProtocolException('INPUT_FILE_UNAVAILABLE'); }
        $result = (new ServiceHistory($this->verifier))->verify($bytes, $installed, []);
        return ['state' => $result['state'], 'record_count' => count($result['admitted']) + 1, 'manifest_hash' => $installed->contentHash];
    }

    private function installed(string $directory): ValidatedRecord
    {
        $path = $directory . '/onion-service-discovery.json';
        if (is_link($path) || !is_file($path) || filesize($path) > 16384) { throw new ProtocolException('EXISTING_MANIFEST_INVALID'); }
        $bytes = file_get_contents($path);
        $record = $bytes === false ? null : $this->verifier->verifyWire($bytes);
        if ($record === null || $record->type !== 'service_manifest') { throw new ProtocolException('EXISTING_MANIFEST_INVALID'); }
        return $record;
    }

    /** @return list<ValidatedRecord> An unreadable or invalid existing bundle is replaced, never trusted. */
    private function load(string $path): array
    {
        if (is_link($path) || !is_file($path) || filesize($path) > ServiceHistory::MAX_BYTES) { return []; }
        try {
            $bundle = StrictJson::parse((string) file_get_contents($path), ServiceHistory::MAX_BYTES);
            if (($bundle->bundle_type ?? null) !== 'service_history' || !is_array($bundle->records ?? null)) { return []; }
            $records = [];
            foreach ($bundle->records as $entry) { if ($entry instanceof \stdClass) { $records[] = $this->verifier->verify($entry); } }
            return $records;
        } catch (\InvalidArgumentException) { return []; }
    }

    /**
     * @param array<string,ValidatedRecord> $candidates
     * @return list<ValidatedRecord>|null Connected records in chronological order, or null.
     */
    private static function connected(ValidatedRecord $installed, array $candidates): ?array
    {
        foreach (IdentityDeriver::derive(array_values($candidates), 10000)['identities'] as $identity) {
            if ($identity['identity_state'] !== 'VALID' || $identity['current_hash'] !== $installed->contentHash) { continue; }
            $hashes = array_merge($identity['history_hashes'], $identity['rotation_hashes'], $identity['migration_hashes']);
            $records = array_values(array_intersect_key($candidates, array_flip($hashes)));
            // Chronological: by revision, each proof directly before the Manifest it authorizes.
            usort($records, static function (ValidatedRecord $a, ValidatedRecord $b): int {
                $key = static fn (ValidatedRecord $record): array => $record->type === 'service_manifest'
                    ? [$record->document()->revision, 1, $record->contentHash] : [$record->document()->effective_revision, 0, $record->contentHash];
                return $key($a) <=> $key($b);
            });
            return $records;
        }
        return null;
    }

    private static function write(string $directory, string $path, string $wire): void
    {
        $temporary = $directory . '/.history-' . bin2hex(random_bytes(12));
        $mask = umask(0077); try { $stream = fopen($temporary, 'x+b'); } finally { umask($mask); }
        if ($stream === false) { throw new ProtocolException('PUBLIC_MANIFEST_WRITE_FAILED'); }
        try {
            if (fwrite($stream, $wire) !== strlen($wire) || !fflush($stream) || !fsync($stream) || !chmod($temporary, 0644)) { throw new ProtocolException('PUBLIC_MANIFEST_WRITE_FAILED'); }
        } catch (\Throwable $error) { fclose($stream); @unlink($temporary); throw $error; }
        fclose($stream);
        if (!rename($temporary, $path)) { @unlink($temporary); throw new ProtocolException('PUBLIC_MANIFEST_WRITE_FAILED'); }
        $handle = fopen($directory, 'r');
        if ($handle === false) { throw new ProtocolException('FILESYSTEM_SEMANTICS_UNSUPPORTED'); }
        try { if (!fsync($handle)) { throw new ProtocolException('FILESYSTEM_SEMANTICS_UNSUPPORTED'); } } finally { fclose($handle); }
    }
}
