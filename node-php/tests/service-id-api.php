<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Node\PublicEvidencePolicy;
use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$vectors = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
$root = sys_get_temp_dir() . '/osd-service-id-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
try {
    $signer = ProtectedFileSigner::create($root . '/node.key', 'node', 'TEST ONLY stable ID API passphrase', $verifier);
    foreach (['filesystem', 'sqlite'] as $backend) {
        foreach ($vectors->cases as $case) {
            if (isset($case->error)) { continue; }
            $dir = $root . '/' . $backend . '-' . $case->id; mkdir($dir, 0700);
            $store = $backend === 'filesystem' ? new FilesystemStore($dir . '/data', create: true) : new SQLiteStore($dir . '/data.sqlite', create: true);
            $blobs = new PublicBlobStore($dir . '/exports', true);
            $records = array_map($verifier->verify(...), $case->records);
            $store->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } });
            $producer = new SnapshotProducer($store, $blobs, $verifier, $signer);
            $producer->publish(1700000000);
            if ($producer->publish(1700000001)['status'] !== 'UNCHANGED') { throw new RuntimeException('idempotency'); }
            $selected = PublicEvidencePolicy::select($records);
            $public = array_values(array_filter($records, static fn ($record) => isset($selected[$record->contentHash])));
            $view = IdentityDeriver::derive($public);
            $api = new DataApi($store, $blobs, $verifier, true);
            $before = $store->snapshot();
            foreach ($view['identities'] as $identity) {
                $path = '/onion-service-discovery/v1/services/' . $identity['genesis_key'];
                $reply = $api->handle('GET', $path); $body = json_decode($reply['body'], true, 64, JSON_THROW_ON_ERROR);
                if ($reply['status'] !== 200 || $body['logical_service_ids'] !== [$identity['genesis_key']]) { throw new RuntimeException('stable lookup: ' . $case->id); }
                $derived = IdentityDeriver::derive(array_map($verifier->verify(...), json_decode($reply['body'])->records));
                if (json_encode($derived['identities'], JSON_THROW_ON_ERROR) !== json_encode([$identity], JSON_THROW_ON_ERROR)) { throw new RuntimeException('lost continuity/fork/withdrawal: ' . $case->id); }
                if ($api->handle('HEAD', $path)['body'] !== '') { throw new RuntimeException('HEAD'); }
                $canonical = $api->handle('GET', '/onion-service-discovery/v1/services/by-id/' . $identity['genesis_key']);
                if ($canonical['status'] !== 200 || $canonical['body'] !== $reply['body']) { throw new RuntimeException('canonical by-id route: ' . $case->id); }
                foreach ($identity['history_hashes'] as $hash) {
                    foreach ($public as $record) {
                        if ($record->contentHash !== $hash) { continue; }
                        $addressReply = $api->handle('GET', '/onion-service-discovery/v1/services/' . $record->document()->onion);
                        $ids = json_decode($addressReply['body'])->logical_service_ids;
                        if (!in_array($identity['genesis_key'], $ids, true)) { throw new RuntimeException('address lost genesis: ' . $case->id); }
                    }
                }
            }
            foreach ($view['pending_hashes'] as $hash) {
                foreach ($public as $record) {
                    if ($record->contentHash !== $hash) { continue; }
                    $reply = $api->handle('GET', '/onion-service-discovery/v1/services/' . $record->document()->service_identity_public_key);
                    if (json_decode($reply['body'])->logical_service_ids !== []) { throw new RuntimeException('orphan minted identity'); }
                }
            }
            if ($api->handle('GET', '/onion-service-discovery/v1/services/ed25519:' . str_repeat('A',43))['status'] !== 400) { throw new RuntimeException('invalid point accepted'); }
            if ($api->handle('GET', '/onion-service-discovery/v1/services/by-id/' . $public[0]->document()->onion)['status'] !== 404) { throw new RuntimeException('by-id accepted an address'); }
            if ($before !== $store->snapshot()) { throw new RuntimeException('lookup mutated storage'); }
            unset($api, $producer, $blobs, $store);
        }
    }
    echo "PASS: stable-ID and address lookup across shared identity histories on both backends\n";
} finally {
    unset($signer);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
}
