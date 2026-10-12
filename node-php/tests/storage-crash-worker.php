<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
[$script, $backend, $path, $point] = $argv;
$hook = static function (string $stage) use ($point): void { if ($stage === $point) { exit(91); } };
$store = $backend === 'filesystem' ? new FilesystemStore($path, create: true, faultHook: $hook) : new SQLiteStore($path, create: true, faultHook: $hook);
$root = dirname(__DIR__, 2);
$cases = json_decode(file_get_contents($root . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($cases->cases as $case) {
    if ($case->id === 'fork-same-revision') {
        $record = (new RecordVerifier($root . '/protocol/schemas'))->verify($case->records[0]);
        $store->transaction(static function (StorageTransaction $tx) use ($record): void {
            $tx->putRecord($record);
            $tx->set('sync', 'crash-peer', ['cursor' => 1]);
        });
        exit(0);
    }
}
exit(92);
