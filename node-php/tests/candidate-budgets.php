<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Node\CandidateAdmission;
use OnionServiceDiscovery\Node\CandidateQueue;
use OnionServiceDiscovery\Node\PeerRegistry;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Storage\StorageLimits;

// Synthetic public address construction only; no Tor keys or network access.
function address(int $number): OnionAddress {
    $public = hash('sha256', 'candidate-budget-fixture:' . $number, true);
    $bytes = $public . substr(hash('sha3-256', '.onion checksum' . $public . "\x03", true), 0, 2) . "\x03";
    $alphabet = 'abcdefghijklmnopqrstuvwxyz234567'; $bits = 0; $value = 0; $encoded = '';
    foreach (str_split($bytes) as $byte) {
        $value = ($value << 8) | ord($byte); $bits += 8;
        while ($bits >= 5) { $bits -= 5; $encoded .= $alphabet[($value >> $bits) & 31]; }
        $value &= (1 << $bits) - 1;
    }
    return OnionAddress::parse($encoded . '.onion');
}
function check(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
$limits = new StorageLimits(); $tx = new StorageTransaction(StorageTransaction::emptyState(), $limits);
for ($i = 0; $i < 33; ++$i) {
    check(CandidateAdmission::enqueue($tx, address($i), 'same-source', 1000) === ($i < 32), 'per-source bounded admission');
}
for ($i = 33; $i < 10000; ++$i) {
    if ($i % 500 === 0) { $tx = new StorageTransaction($tx->state(), $limits); }
    CandidateAdmission::enqueue($tx, address($i), 'rotated-node-' . $i, 1000);
}
check(count($tx->state()['tables']['candidates']) === 256, 'rotating identities bypassed global cap');
$budget = $tx->get('maintenance', 'candidate-admissions');
check(count($budget['sources']) <= 16 && $budget['count'] === 256, 'unbounded source bucket state');
check(!CandidateAdmission::enqueue($tx, address(10000), 'later-source', 999), 'backward clock reset');
check(CandidateAdmission::enqueue($tx, address(10000), 'later-source', 4600), 'window did not resume');
check(!CandidateAdmission::enqueue($tx, address(10001), 'later-source', 1001), 'old clock reopened prior window');
$row = $tx->get('candidates', address(0)->hostname); $row['state'] = 'BACKOFF'; $row['next_attempt'] = 9000; $row['attempts'] = 7;
$tx->set('candidates', address(0)->hostname, $row);
for ($i = 0; $i < 10000; ++$i) { CandidateAdmission::enqueue($tx, address(0), 'duplicate-' . $i, 4600); }
$deduplicated = $tx->get('candidates', address(0)->hostname);
check($deduplicated['next_attempt'] === 9000 && $deduplicated['attempts'] === 7 && count($deduplicated['sources']) === 8, 'duplicate reset retry or attribution unbounded');
for ($i = count($tx->state()['tables']['candidates']); $i < 4096; ++$i) {
    if ($i % 500 === 0) { $tx = new StorageTransaction($tx->state(), $limits); }
    $tx->set('candidates', address(20000 + $i)->hostname, $row);
}
check(!CandidateAdmission::enqueue($tx, address(99999), 'new-source', 8200), 'queue cap bypass');

$root = sys_get_temp_dir() . '/osd-candidate-budget-' . bin2hex(random_bytes(10)); mkdir($root, 0700);
try {
    $store = new SQLiteStore($root . '/state.sqlite', create: true); $queue = new CandidateQueue($store);
    for ($i = 0; $i < 3; ++$i) { check($queue->enqueue(address($i), 'manual', 1000), 'initial admission'); }
    $first = $queue->claim(1000); $second = $queue->claim(1000);
    check($first !== null && $second !== null && $queue->claim(1000) === null, 'global concurrency exceeded');
    $queue->fail($first, 1000, 'TOR_REQUEST_FAILED'); $queue->fail($second, 1000, 'TOR_REQUEST_FAILED');
    // Simulate a spent work budget and restart. The admission clock is independent of the work clock.
    $store->transaction(static function (StorageTransaction $tx): void { $tx->set('maintenance', 'candidate-work', ['start' => 1000, 'last_seen' => 1000, 'count' => 60, 'sources' => []]); });
    unset($queue, $store); $store = new SQLiteStore($root . '/state.sqlite'); $queue = new CandidateQueue($store);
    check($queue->claim(1001) === null && $queue->claim(999) === null, 'restart/clock reset work quota');
    check($queue->claim(4600) !== null, 'work quota never resumed');
    // A clock that ran ahead once must not stall the queue until real time catches up.
    $store->transaction(static function (StorageTransaction $tx): void { $tx->set('maintenance', 'candidate-work', ['start' => 90000000, 'last_seen' => 90000000, 'count' => 1, 'sources' => []]); });
    check($queue->claim(8300) === null, 'clock rollback granted extra work');
    check($queue->claim(11950) !== null, 'work stalled after a clock rollback');
    $key = 'ed25519:' . rtrim(strtr(base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(str_repeat("\x42", 32)))), '+/', '-_'), '=');
    for ($i = 0; $i < 256; ++$i) { check(PeerRegistry::add($store, address($i), $key), 'peer admission'); }
    check(!PeerRegistry::add($store, address(0), $key), 'peer dedup');
    try { PeerRegistry::add($store, address(10000), $key); throw new RuntimeException('peer pool unbounded'); }
    catch (ProtocolException $error) { check($error->getMessage() === 'PEER_POOL_FULL', 'unexpected peer error'); }
    check(count($store->snapshot()['tables']['peers']) === 256, 'existing peers replaced');
} finally {
    unset($store, $queue);
    foreach (glob($root . '/*') as $file) { unlink($file); } rmdir($root);
}
echo "PASS: rotating-source, target-dedup, queue, concurrency, restart, clock and peer-pool budgets\n";
