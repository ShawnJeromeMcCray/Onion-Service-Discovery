<?php
declare(strict_types=1);
// Executable publisher guide: genesis, revision, key rotation, Onion migration and withdrawal
// using only the documented CLI commands (no jq/python), plus the Node-side proof admission.
$project = dirname(__DIR__, 2); $cliPath = $project . '/node-php/bin/onion-service-discovery';
$root = sys_get_temp_dir() . '/osd-lifecycle-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$run = static function (array $arguments, int $expected = 0, ?string $stdout = null) use ($cliPath): array {
    $process = proc_open([PHP_BINARY, $cliPath, ...$arguments], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fclose($pipes[0]); $out = (string) stream_get_contents($pipes[1]); $err = (string) stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expected || $err !== '') { throw new RuntimeException('CLI ' . $arguments[0] . ' exit ' . $exit . ' ' . $out . $err); }
    if ($stdout !== null) { file_put_contents($stdout, $out); }
    return json_decode($out, true, 64, JSON_THROW_ON_ERROR);
};
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$checks = 0;
try {
    chdir($root);
    $addresses = json_decode(file_get_contents($project . '/protocol/test-vectors/onion-addresses.json'), true, 16, JSON_THROW_ON_ERROR)['cases'];
    [$onion, $newOnion] = [$addresses[0]['input'], $addresses[1]['input']];
    foreach (['p-old' => 'TEST ONLY old service passphrase', 'p-new' => 'TEST ONLY new service passphrase'] as $file => $secret) { file_put_contents($file, $secret); chmod($file, 0600); }
    mkdir('web', 0755); file_put_contents('hostname', $onion . "\n");
    file_put_contents('metadata.json', json_encode(['service' => ['name' => 'Lifecycle test', 'description' => 'Synthetic.', 'languages' => ['en'], 'categories' => []],
        'discovery' => ['publish_service' => true, 'mirror_directory' => false, 'discover_services' => false, 'sync_with_peers' => false]], JSON_THROW_ON_ERROR));

    // Genesis.
    $old = $run(['key:create', 'service', 'old.key', '--passphrase-file', 'p-old'])['public_key'];
    $run(['manifest:init', $onion, $old, 'metadata.json'], 0, 'draft-1.json');
    $run(['record:sign', 'service', 'old.key', 'draft-1.json', '--passphrase-file', 'p-old'], 0, 'manifest-1.json');
    $genesisInstall = $run(['manifest:install', 'manifest-1.json', 'hostname', 'web']);
    $assert($genesisInstall['status'] === 'INSTALLED' && $genesisInstall['history'] === 'CONNECTED' && $run(['history:verify', 'web'])['record_count'] === 1, 'genesis install and history'); ++$checks;

    // Ordinary revision.
    $metadata = json_decode(file_get_contents('metadata.json'), true); $metadata['service']['description'] = 'Synthetic, revised.';
    file_put_contents('metadata-2.json', json_encode($metadata, JSON_THROW_ON_ERROR));
    $run(['manifest:revise', 'manifest-1.json', 'metadata-2.json'], 0, 'draft-2.json');
    $run(['record:sign', 'service', 'old.key', 'draft-2.json', '--passphrase-file', 'p-old'], 0, 'manifest-2.json');
    $assert($run(['manifest:install', 'manifest-2.json', 'hostname', 'web'])['status'] === 'INSTALLED', 'revision install'); ++$checks;

    // Key rotation: drafts as separate files, old signs, new countersigns, new signs the successor.
    $new = $run(['key:create', 'service', 'new.key', '--passphrase-file', 'p-new'])['public_key'];
    mkdir('rotation', 0700);
    $drafts = $run(['manifest:rotate', 'manifest-2.json', $new, '--output-dir', 'rotation']);
    $assert($drafts['status'] === 'DRAFTS_WRITTEN' && is_file('rotation/rotation-draft.json') && is_file('rotation/successor-draft.json') && (fileperms('rotation/rotation-draft.json') & 0077) === 0, 'rotation drafts'); ++$checks;
    $assert($run(['manifest:rotate', 'manifest-2.json', $new, '--output-dir', 'rotation'], 3)['error'] === 'OUTPUT_FILE_EXISTS_OR_UNAVAILABLE', 'drafts are never overwritten'); ++$checks;
    $run(['record:sign', 'service', 'old.key', 'rotation/rotation-draft.json', '--passphrase-file', 'p-old'], 0, 'rotation/rotation-old.json');
    $run(['record:sign', 'service', 'new.key', 'rotation/rotation-old.json', '--passphrase-file', 'p-new', '--rotation-confirmation'], 0, 'rotation.json');
    $run(['record:sign', 'service', 'new.key', 'rotation/successor-draft.json', '--passphrase-file', 'p-new'], 0, 'manifest-3.json');
    $assert($run(['protocol:inspect', 'rotation.json'])['record_type'] === 'service_identity_key_rotation', 'rotation proof'); ++$checks;
    $assert($run(['manifest:install', 'manifest-3.json', 'hostname', 'web'], 3)['error'] === 'KEY_ROTATION_INVALID', 'rotation needs its proof'); ++$checks;
    $rotated = $run(['manifest:install', 'manifest-3.json', 'hostname', 'web', '--rotation', 'rotation.json']);
    $assert($rotated['status'] === 'INSTALLED' && $rotated['history'] === 'CONNECTED' && $run(['history:verify', 'web'])['record_count'] === 4, 'rotation install keeps the proof in the history bundle'); ++$checks;

    // Onion migration: the current (new) key signs both records; Tor now serves the new address.
    mkdir('migration', 0700);
    $run(['manifest:migrate', 'manifest-3.json', $newOnion, '--output-dir', 'migration']);
    $run(['record:sign', 'service', 'new.key', 'migration/migration-draft.json', '--passphrase-file', 'p-new'], 0, 'migration.json');
    $run(['record:sign', 'service', 'new.key', 'migration/successor-draft.json', '--passphrase-file', 'p-new'], 0, 'manifest-4.json');
    file_put_contents('hostname', $newOnion . "\n");
    $migrated = $run(['manifest:install', 'manifest-4.json', 'hostname', 'web', '--migration', 'migration.json']);
    $verified = $run(['history:verify', 'web']);
    $assert($migrated['status'] === 'INSTALLED' && $verified['state'] === 'CONNECTED' && $verified['record_count'] === 6, 'migration install keeps a connecting history bundle'); ++$checks;
    // An installation without a bundle (older version) rebuilds it once from the owner's signed files.
    unlink('web/.well-known/onion-service-discovery-history.json');
    $assert($run(['history:verify', 'web'], 6)['state'] === 'MISSING', 'missing bundle reported'); ++$checks;
    $assert($run(['history:install', 'web', 'manifest-1.json', 'manifest-2.json'], 3)['error'] === 'HISTORY_INCOMPLETE', 'incomplete rebuild refused'); ++$checks;
    $rebuilt = $run(['history:install', 'web', 'manifest-1.json', 'manifest-2.json', 'rotation.json', 'manifest-3.json', 'migration.json', 'manifest-4.json']);
    $assert($rebuilt['status'] === 'INSTALLED' && $rebuilt['state'] === 'CONNECTED' && $rebuilt['record_count'] === 6 && $run(['history:verify', 'web'])['state'] === 'CONNECTED', 'history rebuilt'); ++$checks;

    // The complete owner history is passed file by file, without assembling JSON by hand.
    $history = ['manifest-1.json', 'manifest-2.json', 'rotation.json', 'manifest-3.json', 'migration.json', 'manifest-4.json'];
    file_put_contents('publisher.json', json_encode(['configuration_version' => 1, 'role' => 'publisher', 'storage' => ['backend' => 'filesystem', 'path' => $root . '/unused'],
        'export_path' => $root . '/unused-exports', 'node' => ['onion' => $newOnion, 'key_path' => null],
        'features' => ['publish_service' => true, 'mirror_directory' => false, 'discover_services' => false, 'sync_with_peers' => false, 'server_search' => false, 'accept_announcements' => false],
        'transport' => ['proxy_host' => '127.0.0.1', 'proxy_port' => 1, 'timeout_ms' => 500], 'sources' => [], 'allowed_client_origins' => []], JSON_THROW_ON_ERROR));
    $checked = $run(['network:discoverability-check', 'publisher.json', ...$history, '--node', $addresses[2]['input'], $old], 6);
    $assert($checked['expected_state']['revision'] === 4 && $checked['expected_state']['key_epoch'] === 2 && $checked['expected_state']['logical_service_id'] === $old
        && $checked['expected_state']['onion'] === $newOnion && $checked['counts']['unavailable'] === 1, 'history from files ' . json_encode($checked['expected_state'])); ++$checks;

    // Node side: without the proofs the rotated Manifest cannot connect; storage:admit supplies them.
    $run(['storage:init', 'filesystem', 'node-store']);
    foreach (['manifest-1.json', 'manifest-2.json', 'manifest-3.json', 'manifest-4.json'] as $file) { $run(['storage:admit', 'filesystem', 'node-store', $file]); }
    // Read the Node's evidence through its public archive export, not its internal layout.
    $sequence = static function (string $name) use ($run, $root): array {
        $run(['storage:export', 'filesystem', 'node-store', $root . '/' . $name . '.archive']); $records = [];
        foreach (file($root . '/' . $name . '.archive', FILE_IGNORE_NEW_LINES) as $line) { $value = json_decode($line, true, 64, JSON_THROW_ON_ERROR); if (isset($value['protocol'])) { $records[] = $value; } }
        file_put_contents($root . '/' . $name . '.json', json_encode(['records' => $records], JSON_THROW_ON_ERROR));
        return $run(['protocol:apply-sequence', $root . '/' . $name . '.json'])['state'];
    };
    $before = $sequence('before');
    $assert(count($before['identities']) === 1 && $before['identities'][0]['highest_revision'] === 2 && $before['pending_hashes'] !== [], 'Node without proofs stops at revision 2'); ++$checks;
    foreach (['rotation.json', 'migration.json'] as $proof) { $run(['storage:admit', 'filesystem', 'node-store', $proof]); }
    $after = $sequence('after');
    $assert($after['identities'][0]['identity_state'] === 'VALID' && $after['identities'][0]['highest_revision'] === 4 && $after['pending_hashes'] === [], 'proofs connect the history'); ++$checks;

    // Withdrawal is an ordinary signed revision.
    $run(['manifest:withdraw', 'manifest-4.json'], 0, 'draft-5.json');
    $run(['record:sign', 'service', 'new.key', 'draft-5.json', '--passphrase-file', 'p-new'], 0, 'manifest-5.json');
    $assert($run(['manifest:install', 'manifest-5.json', 'hostname', 'web'])['status'] === 'INSTALLED', 'withdrawal install'); ++$checks;
    $served = json_decode((string) file_get_contents('web/.well-known/onion-service-discovery.json'), true, 32, JSON_THROW_ON_ERROR);
    $assert($served['revision'] === 5 && $served['discovery']['publish_service'] === false && $served['onion'] === $newOnion, 'withdrawal served'); ++$checks;
} finally {
    chdir($project);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo "PASS: documented publisher lifecycle (genesis, revision, rotation, migration, withdrawal) and Node proof admission {$checks} checks\n";
