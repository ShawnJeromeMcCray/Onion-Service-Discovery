<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\StorageJson;
use OnionServiceDiscovery\Storage\StorageLimits;
use OnionServiceDiscovery\Storage\StorageTransaction;

function check(bool $condition, string $test): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $test);
    }
}

function temporaryDirectory(): string
{
    $path = sys_get_temp_dir() . '/onion-service-discovery-storage-' . bin2hex(random_bytes(12));
    if (!mkdir($path, 0700)) {
        throw new RuntimeException('Unable to create test directory');
    }
    return $path;
}

function removeTestDirectory(string $root): void
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
        if ($file->isDir() && !$file->isLink()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($root);
}

$root = dirname(__DIR__, 2);
$verifier = new RecordVerifier($root . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($root . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
$forkStory = null;
foreach ($fixtures->cases as $case) {
    if ($case->id === 'fork-same-revision') {
        $forkStory = $case->records;
    }
}
if ($forkStory === null) {
    throw new RuntimeException('Missing fork fixture');
}
[$genesis, $successor, $fork] = array_map(static fn (stdClass $record) => $verifier->verify($record), $forkStory);
$states = [];
$checks = 0;
foreach (['filesystem', 'sqlite'] as $backend) {
    $directory = temporaryDirectory();
    $limits = new StorageLimits(lockWaitMilliseconds: 50);
    $store = $backend === 'filesystem' ? new FilesystemStore($directory . '/data', $limits, true) : new SQLiteStore($directory . '/data.sqlite', $limits, true);
    try {
        check($store->snapshot()['last_sequence'] === 0, $backend . ': empty');
        $store->transaction(static function (StorageTransaction $transaction) use ($genesis): void {
            check($transaction->putRecord($genesis), 'genesis inserted');
            $transaction->set('sync', 'peer-alpha', ['cursor' => 1]);
            $transaction->set('maintenance', '1', ['count' => 1]);
            $transaction->set('maintenance', '01', ['count' => 2]);
        });
        check($store->readRecord($genesis->contentHash) === $genesis->canonicalWire, $backend . ': immutable bytes');
        $before = StorageJson::encode($store->snapshot());
        try {
            $store->transaction(static function (StorageTransaction $transaction) use ($successor): void {
                $transaction->putRecord($successor);
                $transaction->set('sync', 'peer-alpha', ['cursor' => 2]);
                throw new RuntimeException('intentional transaction abort');
            });
            throw new RuntimeException('Expected abort');
        } catch (Throwable $error) {
            check($error->getMessage() !== 'Expected abort', 'abort propagated');
        }
        check(StorageJson::encode($store->snapshot()) === $before, $backend . ': rollback retains cursor and data');
        $store->transaction(static function (StorageTransaction $transaction) use ($successor, $fork): void {
            $transaction->putRecord($successor);
            $transaction->putRecord($fork);
            $transaction->set('sync', 'peer-alpha', ['cursor' => 3]);
        });
        $before = StorageJson::encode($store->snapshot());
        $store->transaction(static fn (StorageTransaction $transaction) => $transaction->putRecord($successor));
        check(StorageJson::encode($store->snapshot()) === $before, $backend . ': duplicate idempotency');
        $records = [];
        foreach (array_keys($store->snapshot()['records']) as $hash) {
            $records[] = $verifier->verifyWire($store->readRecord($hash));
        }
        $view = IdentityDeriver::derive($records);
        check($view['identities'][0]['identity_state'] === 'FORKED' && count($view['identities'][0]['history_hashes']) === 3, $backend . ': fork preserved');
        $store->transaction(static function (StorageTransaction $transaction): void {
            check($transaction->get('maintenance', '1')['count'] === 1, 'numeric key');
            check($transaction->get('maintenance', '01')['count'] === 2, 'leading-zero key');
        });
        try {
            $store->readRecord('../../secrets');
            throw new RuntimeException('Expected invalid address');
        } catch (StorageException $error) {
            check($error->errorCode === 'STORAGE_ADDRESS_INVALID', $backend . ': path injection rejected');
        }
        check($store->integrityCheck()['integrity'] === 'PASS', $backend . ': integrity');
        $second = $backend === 'filesystem' ? new FilesystemStore($directory . '/data', $limits) : new SQLiteStore($directory . '/data.sqlite', $limits);
        $store->transaction(static function (StorageTransaction $transaction) use ($second): void {
            $transaction->set('sync', 'peer-alpha', ['cursor' => 99]);
            $snapshot = $second->snapshot();
            check($snapshot['tables']['sync']['key:peer-alpha']['cursor'] === 3, 'reader sees last committed cursor');
        });
        check($second->snapshot()['tables']['sync']['key:peer-alpha']['cursor'] === 99, $backend . ': reader sees commit');
        $states[$backend] = StorageJson::encode($store->snapshot());
        $checks += 10;
        unset($store, $second);
    } finally {
        removeTestDirectory($directory);
    }
}
check($states['filesystem'] === $states['sqlite'], 'identical backend-neutral state');

foreach (['filesystem', 'sqlite'] as $backend) {
    $directory = temporaryDirectory();
    try {
        $limits = new StorageLimits(maxRecords: 1);
        $store = $backend === 'filesystem' ? new FilesystemStore($directory . '/data', $limits, true) : new SQLiteStore($directory . '/data.sqlite', $limits, true);
        $store->transaction(static fn (StorageTransaction $transaction) => $transaction->putRecord($genesis));
        try {
            $store->transaction(static fn (StorageTransaction $transaction) => $transaction->putRecord($successor));
            throw new RuntimeException('Expected bounded admission');
        } catch (StorageException $error) {
            check($error->errorCode === 'STORAGE_BUDGET_EXHAUSTED', $backend . ': quota');
        }
        check(count($store->snapshot()['records']) === 1, $backend . ': quota failure preserves evidence');
        unset($store);
        $checks += 2;
    } finally {
        removeTestDirectory($directory);
    }
}
echo 'PASS: storage conformance ', $checks + 1, ' contract checks', PHP_EOL;
