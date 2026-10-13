<?php
declare(strict_types=1);

$project = dirname(__DIR__, 2);
$fixtures = json_decode(file_get_contents($project . '/protocol/test-vectors/snapshot-membership.json'), true, 64, JSON_THROW_ON_ERROR);
$root = sys_get_temp_dir() . '/onion-service-discovery-membership-' . bin2hex(random_bytes(12));
mkdir($root, 0700); mkdir($root . '/chunks', 0700);
$selected = ['membership-valid-rotation' => 0, 'membership-stale' => 6, 'membership-fork' => 6,
    'membership-missing-chunk' => 3, 'membership-wrong-node' => 3, 'membership-boolean-is-not-proof' => 3];
try {
    foreach ($fixtures['cases'] as $case) {
        if (!isset($selected[$case['id']])) { continue; }
        foreach (new DirectoryIterator($root . '/chunks') as $file) { if (!$file->isDot()) { unlink($file->getPathname()); } }
        $input = $case['input'];
        file_put_contents($root . '/checkpoint.json', implode('', $input['checkpoint_wire']));
        file_put_contents($root . '/history.json', json_encode(['records' => $input['local_records']], JSON_THROW_ON_ERROR));
        $descriptor = json_decode(implode('', $input['checkpoint_wire']), true, 64, JSON_THROW_ON_ERROR);
        foreach ($input['chunks'] as $index => $wire) {
            if (!isset($descriptor['chunks'][$index])) { continue; }
            $hash = $descriptor['chunks'][$index]['hash'];
            file_put_contents($root . '/chunks/' . substr($hash, 7) . '.json', $wire);
        }
        $command = [PHP_BINARY, $project . '/node-php/bin/onion-service-discovery', 'index:verify-inclusion',
            $root . '/checkpoint.json', $root . '/chunks', $root . '/history.json', $input['expected_node_key'], '--json'];
        $process = proc_open($command, [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes, null,
            ['HTTP_PROXY' => 'http://127.0.0.1:1', 'HTTPS_PROXY' => 'http://127.0.0.1:1']);
        fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
        $actual = json_decode($output, true, 64, JSON_THROW_ON_ERROR);
        $expected = isset($case['error']) ? ['status' => 'FAIL', 'error' => $case['error']] : $case['expected'];
        if ($exit !== $selected[$case['id']] || $error !== '' || $actual !== $expected) { throw new RuntimeException('Offline membership CLI case failed: ' . $case['id']); }
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo "PASS: offline complete-snapshot membership CLI, rotation, stale, fork and invalid proof rejection\n";
