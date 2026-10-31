<?php
declare(strict_types=1);
// Signing ledger recovery beyond signing-ledger.php, on both backends: --adopt-signed-chain admits the own
// checkpoints between the restored store and the signed head (exports:prune keeps their blobs), so a peer
// anchored below the head still proves the chain; a missing one refuses (SIGNED_CHAIN_INCOMPLETE). A crash
// between the commit and the ledger write leaves a pending entry that the next run adopts, also after a
// restore, so a peer that saw the lost generation sees no equivocation. A ledger behind the store whose
// checkpoint blob is gone does not break exports:prune or node:maintain.
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/node-recovery.inc.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Http\DataApi;
use OnionServiceDiscovery\Node\PeerRegistry;
use OnionServiceDiscovery\Node\SigningLedger;
use OnionServiceDiscovery\Node\SnapshotProducer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\PublicBlobStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageTransaction;

$verifier = new RecordVerifier(dirname(__DIR__, 2) . '/protocol/schemas');
$root = sys_get_temp_dir() . '/osd-ledger-recovery-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void { if (!$ok) { throw new RuntimeException($message); } ++$checks; };
$records = recoveryRecords($verifier);
$ledgerOf = static fn (string $path): array => json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
$peerRow = static fn (string $peer, string $onion): array => (new SQLiteStore($peer))->snapshot()['tables']['peers']['key:' . $onion];

try {
    foreach (['filesystem', 'sqlite'] as $backend) {
        // 1. A peer anchored at generation 2; generations 3 and 4 follow; a generation-1 backup is restored and adopted.
        $dir = $root . '/' . $backend . '-gap'; [$writeConfig, $pass, $signer, $onion, $suffix] = recoveryNode($dir, $backend, $verifier);
        $config = $writeConfig('node.json', $dir . '/data' . $suffix); recoveryCli(['node:init', $config, ...$pass], 0);
        $store = NodeConfig::load($config)->store();
        $store->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } });
        $heads = [1 => recoveryCli(['node:snapshot', $config, ...$pass], 0)['checkpoint_hash']];
        recoveryCli(['storage:backup', $backend, $dir . '/data' . $suffix, $dir . '/old.backup'], 0);
        $heads[2] = recoveryCli(['node:snapshot', $config, ...$pass, '--rebuild-derived'], 0)['checkpoint_hash'];
        $blobs = new PublicBlobStore($dir . '/exports'); $peer = $dir . '/peer.sqlite'; $now = time();
        PeerRegistry::add(new SQLiteStore($peer, create: true), OnionAddress::parse($onion), $signer->publicKey());
        $synced = recoveryPeerSync(new DataApi($store, $blobs, $verifier, true), $peer, $onion, $now);
        $assert(($synced['status'] ?? null) === 'CURRENT' && $synced['generation'] === 2, $backend . ': peer anchored at generation 2');
        foreach ([3, 4] as $generation) { $heads[$generation] = recoveryCli(['node:snapshot', $config, ...$pass, '--rebuild-derived'], 0)['checkpoint_hash']; }
        // exports:prune keeps the blobs of the Node's own retained chain, not only the current checkpoint's.
        recoveryCli(['exports:prune', $config, '--workers-stopped'], 0);
        foreach ($heads as $generation => $hash) { $assert($blobs->get($hash) !== null, $backend . ': exports:prune keeps own checkpoint ' . $generation); }
        unset($store);
        recoveryCli(['storage:restore', $backend, $dir . '/restored' . $suffix, $dir . '/old.backup', '--trusted-private-backup'], 0);
        recoveryCli(['storage:restore', $backend, $dir . '/lost' . $suffix, $dir . '/old.backup', '--trusted-private-backup'], 0);
        $restoredConfig = $writeConfig('restored.json', $dir . '/restored' . $suffix); $restored = NodeConfig::load($restoredConfig)->store();
        $adopted = recoveryCli(['node:snapshot', $restoredConfig, ...$pass, '--adopt-signed-chain'], 0);
        $assert($adopted['adopted_signed_chain']['checkpoint'] === 'ADOPTED' && $restored->readRecord($heads[3]) !== null && $restored->readRecord($heads[2]) !== null && $restored->readRecord($heads[4]) !== null, $backend . ': generations 2 to 4 admitted ' . json_encode($adopted));
        $head = $verifier->verifyWire((string) $restored->readRecord($adopted['checkpoint_hash']))->document();
        $assert($head->generation === 5 && $head->previous_checkpoint_hash === $heads[4], $backend . ': generation 5 follows the signed head');
        // The peer walks 5, 4, 3 back to its anchor 2 and stays ACTIVE (before: HTTP_NOT_FOUND at 3 until quarantine).
        $synced = recoveryPeerSync(new DataApi($restored, $blobs, $verifier, true), $peer, $onion, $now + 100000);
        $assert(($synced['status'] ?? null) === 'CURRENT' && $synced['generation'] === 5 && $peerRow($peer, $onion)['state'] === 'ACTIVE', $backend . ': peer below the adopted head continues ' . json_encode($synced));
        $assert((new SQLiteStore($peer))->snapshot()['tables']['sync']['key:' . $onion]['chain_gap'] === false, $backend . ': chain proven without a gap');
        // A missing checkpoint within the newest 64 below the signed head refuses and changes nothing.
        $lostConfig = $writeConfig('lost.json', $dir . '/lost' . $suffix); $lost = NodeConfig::load($lostConfig)->store(); $before = $lost->snapshot();
        $third = $dir . '/exports/' . substr($heads[3], 7) . '.json'; rename($third, $dir . '/third.json');
        $refused = recoveryCli(['node:snapshot', $lostConfig, ...$pass, '--adopt-signed-chain'], 4);
        $assert($refused['error'] === 'SIGNED_CHAIN_INCOMPLETE' && isset($refused['meaning']) && $lost->snapshot() === $before, $backend . ': missing intermediate checkpoint refused ' . json_encode($refused));
        rename($dir . '/third.json', $third);
        $assert(recoveryCli(['node:snapshot', $lostConfig, ...$pass, '--adopt-signed-chain'], 0)['adopted_signed_chain']['checkpoint'] === 'ADOPTED', $backend . ': adoption after the file is restored');
        unset($restored, $lost);

        // 2. Crash after the commit, before the ledger write; then a backup from before that generation is restored.
        $dir = $root . '/' . $backend . '-crash'; [$writeConfig, $pass, $signer, $onion, $suffix] = recoveryNode($dir, $backend, $verifier);
        $config = $writeConfig('node.json', $dir . '/data' . $suffix); recoveryCli(['node:init', $config, ...$pass], 0);
        $store = NodeConfig::load($config)->store();
        $store->transaction(static function (StorageTransaction $tx) use ($records): void { foreach ($records as $record) { $tx->putRecord($record); } });
        recoveryCli(['node:snapshot', $config, ...$pass], 0);
        recoveryCli(['storage:backup', $backend, $dir . '/data' . $suffix, $dir . '/old.backup'], 0);
        $crashing = new class ($store) implements StorageBackend {
            public function __construct(private StorageBackend $inner) {}
            public function backendName(): string { return $this->inner->backendName(); }
            public function supportsPruning(): bool { return $this->inner->supportsPruning(); }
            public function transaction(callable $mutation): mixed
            {
                $result = $this->inner->transaction($mutation);
                if (($this->inner->snapshot()['tables']['node']['key:published-snapshot']['generation'] ?? 0) === 2) { throw new RuntimeException('process died after the commit'); }
                return $result;
            }
            public function snapshot(): array { return $this->inner->snapshot(); }
            public function readRecord(string $contentHash): ?string { return $this->inner->readRecord($contentHash); }
            public function readRecords(array $contentHashes): array { return $this->inner->readRecords($contentHashes); }
            public function integrityCheck(): array { return $this->inner->integrityCheck(); }
        };
        $blobs = new PublicBlobStore($dir . '/exports'); $ledgerPath = $dir . '/node.key.signed';
        try { (new SnapshotProducer($crashing, $blobs, $verifier, $signer, false, true, SigningLedger::forKey($dir . '/node.key')))->publish(time()); throw new LogicException('no crash'); }
        catch (RuntimeException $error) { $assert($error->getMessage() === 'process died after the commit', $backend . ': crash after the commit'); }
        $lostGeneration = $store->snapshot()['tables']['node']['key:published-snapshot']['hash'];
        $assert($ledgerOf($ledgerPath)['checkpoint']['generation'] === 1 && $ledgerOf($ledgerPath)['pending_checkpoint'] === ['generation' => 2, 'hash' => $lostGeneration], $backend . ': generation 2 pending in the ledger');
        $peer = $dir . '/peer.sqlite'; $now = time(); PeerRegistry::add(new SQLiteStore($peer, create: true), OnionAddress::parse($onion), $signer->publicKey());
        $synced = recoveryPeerSync(new DataApi($store, $blobs, $verifier, true), $peer, $onion, $now);
        $assert(($synced['status'] ?? null) === 'CURRENT' && $synced['generation'] === 2, $backend . ': peer saw the generation the ledger missed');
        unset($store, $crashing);
        recoveryCli(['storage:restore', $backend, $dir . '/restored' . $suffix, $dir . '/old.backup', '--trusted-private-backup'], 0);
        $restoredConfig = $writeConfig('restored.json', $dir . '/restored' . $suffix); $restored = NodeConfig::load($restoredConfig)->store();
        $assert(recoveryCli(['node:status', $restoredConfig], 6)['signing_ledger'] === 'AHEAD_OF_STORE', $backend . ': node:status detects the pending generation (before: CONSISTENT)');
        // The next snapshot adopts the pending generation 2 from its blob and continues at 3, never signing another 2.
        $published = recoveryCli(['node:snapshot', $restoredConfig, ...$pass, '--rebuild-derived'], 0);
        $head = $verifier->verifyWire((string) $restored->readRecord($published['checkpoint_hash']))->document(); $ledger = $ledgerOf($ledgerPath);
        $assert($head->generation === 3 && $head->previous_checkpoint_hash === $lostGeneration && $ledger['checkpoint'] === ['generation' => 3, 'hash' => $published['checkpoint_hash']] && !isset($ledger['pending_checkpoint']), $backend . ': pending generation adopted');
        $synced = recoveryPeerSync(new DataApi($restored, $blobs, $verifier, true), $peer, $onion, $now + 100000);
        $assert(($synced['status'] ?? null) === 'CURRENT' && $synced['generation'] === 3 && $peerRow($peer, $onion)['state'] === 'ACTIVE', $backend . ': no equivocation for the peer ' . json_encode($synced));
        $assert(recoveryCli(['node:status', $restoredConfig], 0)['signing_ledger'] === 'CONSISTENT', $backend . ': ledger consistent again');
        // An older ledger file without pending entries (written by older releases) still loads.
        $assert(array_keys($ledgerOf($ledgerPath)) === ['checkpoint', 'descriptor', 'ledger_version', 'node_identity_public_key'], $backend . ': settled ledger has the version 1 shape');

        // 3. A ledger copy behind the store whose checkpoint blob is gone: export pruning and maintenance still run.
        copy($ledgerPath, $dir . '/ledger-copy'); $old = $ledger['checkpoint']['hash'];
        recoveryCli(['node:snapshot', $restoredConfig, ...$pass, '--rebuild-derived'], 0);
        copy($dir . '/ledger-copy', $ledgerPath); chmod($ledgerPath, 0600); unlink($dir . '/exports/' . substr($old, 7) . '.json');
        $assert(recoveryCli(['node:status', $restoredConfig], 0)['signing_ledger'] === 'BEHIND_STORE', $backend . ': ledger behind the store');
        $assert(recoveryCli(['exports:prune', $restoredConfig, '--workers-stopped'], 0)['status'] === 'PASS', $backend . ': exports:prune with the ledger checkpoint blob gone (before: EXPORT_CORRUPT)');
        $assert(recoveryCli(['node:maintain', $restoredConfig], 0)['status'] === 'PASS', $backend . ': node:maintain with the ledger checkpoint blob gone');
        unset($restored);
    }
} finally { recoveryRemove($root); }
echo "PASS: signing ledger recovery bridges adopted chains, adopts pending checkpoints after a crash and tolerates pruned ledger blobs {$checks} checks\n";
