<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\StorageException;
use OnionServiceDiscovery\Storage\StorageTransaction;

$base = __DIR__ . '/fixtures/filesystem-v1';
$checks = 0;
$copy = static function (string $source, string $target): void {
    mkdir($target, 0700);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $file) {
        $path = $target . '/' . substr($file->getPathname(), strlen($source) + 1);
        if ($file->isLink()) { throw new RuntimeException('Fixture symlink forbidden'); }
        if ($file->isDir()) { mkdir($path, 0700); }
        else { copy($file->getPathname(), $path); chmod($path, 0600); }
    }
};
$clean = static function (string $path): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($path);
};
foreach (['stable' => 2, 'pending-head' => 2, 'pending-prepare' => 1] as $fixture => $expectedCount) {
    $path = sys_get_temp_dir() . '/format-migration-' . bin2hex(random_bytes(12));
    $copy($base . '/' . $fixture, $path);
    try {
        try { new FilesystemStore($path); throw new RuntimeException('Legacy write mode allowed'); }
        catch (StorageException $error) { if ($error->getMessage() !== 'STORAGE_MIGRATION_REQUIRED') { throw $error; } ++$checks; }
        $before = (new FilesystemStore($path, readOnly: true))->snapshot();
        $result = FilesystemStore::migrateFormat($path);
        $store = new FilesystemStore($path);
        if ($result['status'] !== 'MIGRATED' || $store->formatVersion() !== 2 || $store->snapshot() !== $before || count($before['records']) !== $expectedCount || $before['tables']['sync']['key:migration-peer']['cursor'] !== $expectedCount) { throw new RuntimeException('Legacy state was reinterpreted'); }
        ++$checks;
        $store->integrityCheck();
        if (FilesystemStore::migrateFormat($path)['status'] !== 'UNCHANGED') { throw new RuntimeException('Migration not idempotent'); } ++$checks;
        $store->transaction(static function (StorageTransaction $tx): void { $tx->set('maintenance', 'post-migration', ['completed' => true]); });
        $store->integrityCheck(); ++$checks;
    } finally { unset($store); $clean($path); }
}
foreach (['after_legacy_recovery','after_format_switch'] as $point) {
    $path = sys_get_temp_dir() . '/format-migration-' . bin2hex(random_bytes(12));
    $copy($base . '/pending-head', $path);
    try {
        $process = proc_open([PHP_BINARY,__DIR__ . '/storage-migration-worker.php',$path,$point], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
        if ($process === false) { throw new RuntimeException('Migration worker unavailable'); }
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if (proc_close($process) !== 91 || $out !== '' || $err !== '') { throw new RuntimeException('Migration crash was not injected'); }
        FilesystemStore::migrateFormat($path);
        $store = new FilesystemStore($path);
        if (count($store->snapshot()['records']) !== 2 || $store->snapshot()['tables']['sync']['key:migration-peer']['cursor'] !== 2) { throw new RuntimeException('Migration crash lost committed history'); }
        $store->integrityCheck(); ++$checks;
    } finally { unset($store); $clean($path); }
}
$path = sys_get_temp_dir() . '/format-migration-' . bin2hex(random_bytes(12));
$copy($base . '/stable', $path);
try {
    $object = glob($path . '/objects/*/*.json')[0];
    file_put_contents($object, 'corrupted fixture');
    $journal = file_get_contents($path . '/journal.log');
    try { FilesystemStore::migrateFormat($path); throw new RuntimeException('Corrupt object migrated'); }
    catch (StorageException $error) { if ($error->getMessage() !== 'STORAGE_CORRUPT') { throw $error; } }
    if (file_get_contents($path . '/storage-version') !== '1' || file_get_contents($path . '/journal.log') !== $journal) { throw new RuntimeException('Failed preflight changed format/journal'); } ++$checks;
} finally { $clean($path); }
echo 'PASS: versioned filesystem migration ' . $checks . " checks\n";
