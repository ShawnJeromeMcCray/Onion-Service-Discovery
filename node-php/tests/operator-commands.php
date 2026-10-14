<?php
declare(strict_types=1);
// Spec 32/04 command names, backend migration and the redacted support bundle.
$project = dirname(__DIR__, 2); $cli = $project . '/node-php/bin/onion-service-discovery';
$root = sys_get_temp_dir() . '/osd-operator-commands-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
function run(array $arguments, int $expected): array
{
    global $cli;
    $process = proc_open([PHP_BINARY, $cli, ...$arguments, '--json', '--no-interaction'], [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expected || $err !== '') { throw new RuntimeException('CLI ' . $arguments[0] . ' exit ' . $exit . ' ' . $out . $err); }
    return json_decode($out, true, 64, JSON_THROW_ON_ERROR);
}
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$checks = 0;
try {
    $records = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
    foreach ($records->cases as $case) { if ($case->id === 'valid-rotation') { $sequence = $case->records; } }
    foreach (['filesystem' => 'sqlite', 'sqlite' => 'filesystem'] as $from => $to) {
        $source = $root . '/source-' . $from . ($from === 'sqlite' ? '.sqlite' : '');
        $assert(run(['storage:init', $from, $source], 0)['status'] === 'PASS', 'init ' . $from); ++$checks;
        foreach ($sequence as $index => $record) {
            file_put_contents($root . '/record-' . $index . '.json', json_encode($record, JSON_THROW_ON_ERROR));
            run(['storage:admit', $from, $source, $root . '/record-' . $index . '.json'], 0);
        }
        $verified = run(['storage:verify', $from, $source], 0);
        $assert($verified['status'] === 'PASS', 'storage:verify alias'); ++$checks;
        $target = $root . '/target-' . $to . ($to === 'sqlite' ? '.sqlite' : '');
        $migrated = run(['storage:migrate-backend', $from, $source, $to, $target], 0);
        $assert($migrated['record_count'] === count($sequence) && $migrated['staging_only'] === true && $migrated['to_backend'] !== $migrated['from_backend'], 'migration ' . $from); ++$checks;
        $assert(run(['storage:check', $to, $target], 0)['status'] === 'PASS', 'migrated integrity ' . $to); ++$checks;
        $assert(glob($root . '/.osd-migrate-*') === [], 'migration carrier removed'); ++$checks;
        $assert(run(['storage:migrate-backend', $from, $source, $to, $target], 4)['error'] === 'RESTORE_REQUIRES_EMPTY_STAGING_STORE', 'overwrite refused'); ++$checks;
    }
    $assert(run(['storage:migrate-backend', 'filesystem', $root . '/source-filesystem', 'mysql', $root . '/x'], 3)['error'] === 'STORAGE_BACKEND_INVALID', 'unknown backend'); ++$checks;
    $assert(run(['key:node:rotate'], 3)['error'] === 'NODE_KEY_ROTATION_UNSUPPORTED', 'node rotation explicit refusal'); ++$checks;
    $help = implode("\n", run(['help'], 0)['commands']);
    foreach (['storage:migrate-backend', 'diagnostics:export', 'storage:verify', 'snapshot:build', 'peer:remove', 'key:service:rotate', 'key:node:rotate'] as $name) {
        $assert(str_contains($help, $name), 'help lists ' . $name); ++$checks;
    }
    $assert(run(['key:service:rotate', $root . '/missing.json', 'ed25519:x'], 3)['status'] === 'FAIL', 'key:service:rotate reaches manifest:rotate'); ++$checks;

    // Support bundle redaction with known private fixture values.
    $v = json_decode(file_get_contents($project . '/node-php/config/node.example.json'), true, 32, JSON_THROW_ON_ERROR);
    $secretDir = $root . '/SECRET-DIRECTORY-MARKER';
    mkdir($secretDir, 0700);
    $v['storage']['path'] = $secretDir . '/data'; $v['export_path'] = $secretDir . '/exports'; $v['node']['key_path'] = $secretDir . '/node.key';
    $v['node']['onion'] = $sequence[0]->onion;
    file_put_contents($secretDir . '/config.json', json_encode($v, JSON_THROW_ON_ERROR)); chmod($secretDir . '/config.json', 0600);
    $bundle = run(['diagnostics:export', $secretDir . '/config.json'], 0);
    $wire = json_encode($bundle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $assert($bundle['bundle_version'] === 1 && isset($bundle['diagnostics']['status'], $bundle['runtime']['php_version']), 'bundle content'); ++$checks;
    $assert(!str_contains($wire, 'SECRET-DIRECTORY-MARKER') && !str_contains($wire, $root) && !str_contains($wire, $sequence[0]->onion) && !str_contains($wire, 'ed25519:'), 'bundle leaked private value'); ++$checks;
    $assert($bundle['configuration']['node_onion_configured'] === true && $bundle['network_side_effects'] === false, 'bundle summary'); ++$checks;
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo "PASS: operator command names, backend migration and redacted support bundle {$checks} checks\n";
