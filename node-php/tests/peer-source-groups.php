<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Node\PeerRegistry;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;

$project = dirname(__DIR__, 2);
$stories = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), true, 64, JSON_THROW_ON_ERROR)['cases'];
$keys = [];
foreach ($stories as $case) {
    foreach ($case['records'] as $record) {
        if ($record['record_type'] === 'service_manifest') { $keys[$record['service_identity_public_key']] = true; }
    }
}
$keys = array_keys($keys);
$addresses = [];
foreach (json_decode(file_get_contents($project . '/protocol/test-vectors/onion-addresses.json'), true)['cases'] as $case) {
    if ($case['valid']) { $addresses[OnionAddress::parse($case['input'])->hostname] = true; }
}
$addresses = array_map(OnionAddress::parse(...), array_keys($addresses));
$root = sys_get_temp_dir() . '/onion-service-discovery-source-groups-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
try {
    foreach (['filesystem', 'sqlite'] as $backend) {
        $store = $backend === 'filesystem' ? new FilesystemStore($root . '/data', create: true) : new SQLiteStore($root . '/data.sqlite', create: true);
        foreach ([0,1] as $index) { PeerRegistry::add($store, $addresses[$index], $keys[$index]); }
        $store->transaction(static function (StorageTransaction $tx) use ($addresses): void {
            foreach ([0,1] as $index) { $row = $tx->get('peers', $addresses[$index]->hostname); $row['state'] = 'ACTIVE'; $tx->set('peers', $addresses[$index]->hostname, $row); }
        });
        $inspect = static fn (): array => PeerRegistry::concentration($store->snapshot()['tables']['peers']);
        $unknown = $inspect();
        if ($unknown['configured_source_groups'] !== 0 || $unknown['ungrouped_node_identities'] !== 2 || $unknown['operator_independence'] !== 'NOT_PROVEN') { throw new RuntimeException('Unknown operators inferred from keys'); }
        foreach ([0,1] as $index) { PeerRegistry::group($store, $addresses[$index], 'same-operator'); }
        $grouped = $inspect();
        if ($grouped['configured_source_groups'] !== 1 || !$grouped['concentrated'] || $grouped['distinct_node_identities'] !== 2 || $grouped['trust_authority_from_count']) { throw new RuntimeException('Shared operator inflation'); }
        PeerRegistry::group($store, $addresses[1], 'selected-family-b');
        if ($inspect()['configured_source_groups'] !== 2 || $inspect()['concentrated']) { throw new RuntimeException('Local source grouping lost'); }
        $before = $store->snapshot();
        foreach (['', 'WrongCase', str_repeat('x', 33), '../a', 'a-', "a\n", '💡'] as $bad) {
            try { PeerRegistry::group($store, $addresses[0], $bad); throw new RuntimeException('Invalid group accepted'); }
            catch (ProtocolException $error) { if ($error->getMessage() !== 'SOURCE_GROUP_INVALID') { throw $error; } }
        }
        if ($store->snapshot() !== $before) { throw new RuntimeException('Rejected grouping mutated store'); }
        $rows = $before['tables']['peers'];
        $rows['key:' . $addresses[1]->hostname]['node_identity_public_key'] = $keys[0];
        $sameKey = PeerRegistry::concentration($rows);
        if ($sameKey['distinct_node_identities'] !== 1 || $sameKey['configured_source_groups'] !== 0 || !$sameKey['grouping_inconsistent']) { throw new RuntimeException('One key counted as several groups'); }
        $store->transaction(static function (StorageTransaction $tx) use ($addresses): void {
            $row = $tx->get('peers', $addresses[0]->hostname); $row['claim'] = 'test-only-busy-claim'; $tx->set('peers', $addresses[0]->hostname, $row);
        });
        try { PeerRegistry::group($store, $addresses[0], 'racing-change'); throw new RuntimeException('In-flight group change accepted'); }
        catch (ProtocolException $error) { if ($error->getMessage() !== 'PEER_BUSY') { throw $error; } }
        PeerRegistry::group($store, $addresses[1], null);
        unset($store);
        $reopened = $backend === 'filesystem' ? new FilesystemStore($root . '/data', readOnly: true) : new SQLiteStore($root . '/data.sqlite', readOnly: true);
        $persisted = PeerRegistry::concentration($reopened->snapshot()['tables']['peers']);
        if ($persisted['configured_source_groups'] !== 1 || $persisted['ungrouped_node_identities'] !== 1) { throw new RuntimeException('Grouping not durable'); }
        $reopened->integrityCheck(); unset($reopened);
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
}
echo "PASS: local source groups, identity-count neutrality, concurrency refusal and both-backend persistence\n";
