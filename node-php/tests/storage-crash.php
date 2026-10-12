<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
$checks = 0;
foreach (['filesystem' => ['after_objects', 'after_prepare', 'after_head', 'after_commit'], 'sqlite' => ['after_objects', 'after_records', 'before_cursor_commit', 'after_commit']] as $backend => $points) {
    foreach ($points as $point) {
        $root = sys_get_temp_dir() . '/osd-crash-' . bin2hex(random_bytes(12));
        mkdir($root, 0700);
        $path = $root . ($backend === 'filesystem' ? '/data' : '/data.sqlite');
        $process = proc_open([PHP_BINARY, __DIR__ . '/storage-crash-worker.php', $backend, $path, $point], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if ($process === false) { throw new RuntimeException('worker unavailable'); }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 91) { throw new RuntimeException('fault not reached: ' . $backend . '/' . $point . $stdout . $stderr); }
        $store = $backend === 'filesystem' ? new FilesystemStore($path) : new SQLiteStore($path);
        $state = $store->snapshot();
        $committed = in_array($point, ['after_head', 'after_commit'], true);
        if (count($state['records']) !== (int) $committed || $state['last_sequence'] !== (int) $committed) { throw new RuntimeException('atomic record recovery failed'); }
        $cursor = null;
        $store->transaction(static function (StorageTransaction $tx) use (&$cursor): void { $cursor = $tx->get('sync', 'crash-peer'); });
        if (($cursor['cursor'] ?? 0) !== (int) $committed) { throw new RuntimeException('atomic cursor recovery failed'); }
        $store->integrityCheck();
        unset($store);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($root);
        ++$checks;
    }
}
echo 'PASS: storage crash recovery ' . $checks . " fault points\n";
