<?php
declare(strict_types=1);
// `init` writes a valid configuration for every role with private paths inside its own directory.
$project = dirname(__DIR__, 2); $cliPath = $project . '/node-php/bin/onion-service-discovery';
$root = sys_get_temp_dir() . '/osd-init-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$run = static function (array $arguments, int $expected) use ($cliPath): array {
    $process = proc_open([PHP_BINARY, $cliPath, ...$arguments, '--json'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fclose($pipes[0]); $out = (string) stream_get_contents($pipes[1]); $err = (string) stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expected || $err !== '') { throw new RuntimeException('CLI ' . implode(' ', array_slice($arguments, 0, 4)) . ' exit ' . $exit . ' ' . $out . $err); }
    return json_decode($out, true, 32, JSON_THROW_ON_ERROR);
};
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$checks = 0;
try {
    $cases = json_decode(file_get_contents($project . '/protocol/test-vectors/onion-addresses.json'), true, 16, JSON_THROW_ON_ERROR)['cases'];
    [$onion, $source, $second] = [$cases[0]['input'], $cases[1]['input'], $cases[2]['input']];
    foreach (['publisher', 'node', 'sqlite', 'combined', 'client', 'unsafe', 'usage'] as $name) { mkdir($root . '/' . $name, $name === 'unsafe' ? 0755 : 0700); chmod($root . '/' . $name, $name === 'unsafe' ? 0755 : 0700); }

    $publisher = $run(['init', $root . '/publisher/config.json', $onion, '--publish'], 0);
    $assert($publisher['role'] === 'publisher' && $publisher['publishing_opt_in'] === true && is_string($publisher['next_action']), 'publisher init unchanged'); ++$checks;

    $node = $run(['init', $root . '/node/config.json', $onion, '--role', 'node'], 0);
    $written = json_decode(file_get_contents($root . '/node/config.json'), true, 16, JSON_THROW_ON_ERROR);
    $assert($node['role'] === 'node' && $written['node']['key_path'] === $root . '/node/node.key' && $written['storage']['path'] === $root . '/node/data'
        && $written['export_path'] === $root . '/node/exports' && $written['features']['mirror_directory'] && $written['features']['discover_services'] && !$written['features']['sync_with_peers']
        && (fileperms($root . '/node/config.json') & 0077) === 0 && $run(['config:validate', $root . '/node/config.json'], 0)['status'] === 'PASS', 'node init'); ++$checks;
    // The next steps printed by init work as written.
    file_put_contents($root . '/node/passphrase', 'TEST ONLY init node passphrase'); chmod($root . '/node/passphrase', 0600);
    $run(['key:create', 'node', $root . '/node/node.key', '--passphrase-file', $root . '/node/passphrase'], 0);
    $run(['node:init', $root . '/node/config.json', '--passphrase-file', $root . '/node/passphrase'], 0);
    $assert($run(['node:identity', $root . '/node/config.json'], 0)['node_onion'] === $onion, 'node next steps'); ++$checks;

    $sqlite = $run(['init', $root . '/sqlite/config.json', $onion, '--role', 'node', '--sqlite'], 0);
    $assert($sqlite['role'] === 'node' && json_decode(file_get_contents($root . '/sqlite/config.json'), true)['storage'] === ['backend' => 'sqlite', 'path' => $root . '/sqlite/data.sqlite'], 'sqlite init'); ++$checks;

    $combined = $run(['init', $root . '/combined/config.json', $onion, '--role', 'combined', '--source', $source, '--visitor-sources'], 0);
    $assert($combined['role'] === 'combined' && $combined['source_count'] === 1 && $combined['features']['visitor_sources'] === true, 'combined init'); ++$checks;

    $client = $run(['init', $root . '/client/config.json', '--role', 'client-host', '--source', $source, '--source', $second, '--visitor-sources'], 0);
    $written = json_decode(file_get_contents($root . '/client/config.json'), true, 16, JSON_THROW_ON_ERROR);
    $assert($client['role'] === 'client-host' && $written['sources'] === [$source, $second] && $written['node'] === ['onion' => null, 'key_path' => null]
        && $written['features']['visitor_sources'] === true && array_filter($written['features']) === ['visitor_sources' => true], 'client host init'); ++$checks;
    $assert($run(['client:init', $root . '/client/config.json'], 0)['status'] === 'INITIALIZED', 'client host next step'); ++$checks;

    $assert($run(['init', $root . '/unsafe/config.json', '--role', 'client-host'], 3)['error'] === 'CONFIGURATION_PATH_UNSAFE', 'shared directory refused'); ++$checks;
    $assert($run(['init', $root . '/client/config.json', '--role', 'client-host'], 3)['error'] === 'CONFIGURATION_CREATE_REFUSED', 'existing file never overwritten'); ++$checks;
    foreach ([['--role', 'client-host', $onion], ['--role', 'node'], [$onion, '--role', 'publisher', '--source', $source], [$onion, '--role', 'node', '--visitor-sources'], [$onion, '--sqlite'], [$onion, '--role', 'relay'], [$onion, '--unknown']] as $invalid) {
        $assert($run(['init', $root . '/usage/config.json', ...$invalid], 2)['error'] === 'USAGE_ERROR', 'invalid init accepted: ' . implode(' ', $invalid)); ++$checks;
    }
    $assert(!file_exists($root . '/usage/config.json'), 'refused init wrote a file'); ++$checks;
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo "PASS: init for publisher, node, combined and Client Host roles {$checks} checks\n";
