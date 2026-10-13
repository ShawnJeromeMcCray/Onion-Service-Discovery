<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Storage\StorageException;
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
        if ($backend === 'filesystem' && in_array($point, ['after_prepare', 'after_commit'], true)) {
            $journalBefore = file_get_contents($path . '/journal.log');
            try { new FilesystemStore($path, readOnly: true); throw new RuntimeException('read-only recovery silently accepted'); }
            catch (\OnionServiceDiscovery\Storage\StorageException $error) { if ($error->getMessage() !== 'STORAGE_RECOVERY_REQUIRED') { throw $error; } }
            if (file_get_contents($path . '/journal.log') !== $journalBefore) { throw new RuntimeException('read-only diagnostics repaired journal'); }
        }
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
$root = sys_get_temp_dir() . '/osd-uncommitted-head-' . bin2hex(random_bytes(12)); mkdir($root,0700); $path = $root . '/data';
$process = proc_open([PHP_BINARY,__DIR__ . '/storage-crash-worker.php','filesystem',$path,'after_prepare'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
if (proc_close($process) !== 91) { throw new RuntimeException('Uncommitted HEAD fixture unavailable'); }
$lines = explode("\n",rtrim(file_get_contents($path . '/journal.log'),"\n"));$prepare=json_decode($lines[count($lines)-1],true,16,JSON_THROW_ON_ERROR);
file_put_contents($path . '/HEAD',\OnionServiceDiscovery\Storage\StorageJson::encode(['generation'=>$prepare['generation'],'checkpoint'=>$prepare['checkpoint']]));
$journal=file_get_contents($path . '/journal.log');
foreach ([true,false] as $readOnly) {
    try { new FilesystemStore($path,readOnly:$readOnly);throw new RuntimeException('PREPARE-only HEAD accepted as current'); }
    catch (StorageException $error) { if ($error->getMessage() !== 'STORAGE_CORRUPT') { throw $error; } }
    if (file_get_contents($path . '/journal.log') !== $journal) { throw new RuntimeException('Corrupt HEAD fabricated a COMMIT'); } ++$checks;
}
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($iterator as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
echo 'PASS: storage crash recovery ' . $checks . " fault points\n";
