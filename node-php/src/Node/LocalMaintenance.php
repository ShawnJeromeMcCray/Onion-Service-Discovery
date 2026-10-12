<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageTransaction;

/** Bounded housekeeping of replaceable remote assertions, never identity or fork evidence. */
final readonly class LocalMaintenance
{
    public function __construct(private StorageBackend $store) {}
    /** @return array<string,int> */
    public function run(int $now): array
    {
        if ($now < 0 || $now > 9007199254740991) { throw new ProtocolException('CLOCK_INVALID'); }
        return $this->store->transaction(static function (StorageTransaction $tx) use ($now): array {
            $state = $tx->state(); $keys = array_keys($state['tables']['assertions']); sort($keys, SORT_STRING);
            $count = count($keys); $offset = ($tx->get('maintenance', 'assertion-scan')['offset'] ?? 0) % max(1, $count);
            $scanned = min(256, $count); $removed = 0;
            for ($index = 0; $index < $scanned; ++$index) {
                $key = substr((string)$keys[($offset + $index) % $count], 4); $row = $tx->get('assertions', $key);
                if ($row === null || ($row['kind'] ?? null) !== 'REMOTE_PROVENANCE' || !is_array($row['sources'] ?? null)) { throw new ProtocolException('ASSERTION_STATE_INVALID'); }
                $sources = array_values(array_filter($row['sources'], static fn (array $source): bool => $source['expires_at'] > $now));
                if ($sources === []) { $tx->deleteOperational('assertions', $key); ++$removed; }
                elseif ($sources !== $row['sources']) { $row['sources'] = $sources; $tx->set('assertions', $key, $row); }
            }
            $tx->set('maintenance', 'assertion-scan', ['offset' => ($offset + $scanned) % max(1, $count - $removed)]);
            return ['assertions_scanned' => $scanned, 'assertions_removed' => $removed, 'signed_records_removed' => 0];
        });
    }
}
