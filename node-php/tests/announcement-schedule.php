<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\AnnouncementSchedule;
use OnionServiceDiscovery\Configuration\PublisherEvidence;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;

// D-018 bounded automatic announcement: only a new revision or a missing observation, at most
// once per day per Node, exponential backoff, and a hint never counts as an observation.

/** Deterministic synthetic v3 Onion for a TEST Node; no Tor identity is created. */
function testOnion(int $seed): string
{
    $key = substr(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(str_repeat(chr($seed), 32))), 0, 32);
    $bytes = $key . substr(hash('sha3-256', '.onion checksum' . $key . "\x03", true), 0, 2) . "\x03";
    $alphabet = 'abcdefghijklmnopqrstuvwxyz234567'; $bits = ''; $out = '';
    foreach (str_split($bytes) as $byte) { $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT); }
    foreach (str_split($bits, 5) as $chunk) { $out .= $alphabet[bindec($chunk)]; }
    return OnionAddress::parse($out . '.onion')->hostname;
}
function readExact($socket, int $length): string
{
    $bytes = ''; while (strlen($bytes) < $length) { $part = fread($socket, $length - strlen($bytes)); if ($part === false || $part === '') { throw new RuntimeException('short SOCKS message'); } $bytes .= $part; } return $bytes;
}
/**
 * Runs the CLI against a local SOCKS stub that answers each Node with the given HTTP response.
 * @param array<string,string> $responses Node hostname => raw HTTP response.
 * @return array{0:int,1:array<string,mixed>,2:list<string>} Exit code, JSON output and contacted Nodes in order.
 */
function announce(array $arguments, $server, array $responses, string $service): array
{
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/onion-service-discovery', ...$arguments, '--json'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fclose($pipes[0]); $contacted = [];
    stream_set_blocking($pipes[1], false); $output = '';
    while (true) {
        $output .= (string) stream_get_contents($pipes[1]);
        if (!proc_get_status($process)['running']) { break; }
        $read = [$server]; $write = $except = null;
        if (@stream_select($read, $write, $except, 0, 50000) !== 1) { continue; }
        $socket = stream_socket_accept($server, 1); if ($socket === false) { continue; }
        stream_set_timeout($socket, 5);
        $greeting = readExact($socket, 2); readExact($socket, ord($greeting[1])); fwrite($socket, "\x05\x02");
        $auth = readExact($socket, 2); readExact($socket, ord($auth[1])); readExact($socket, ord(readExact($socket, 1))); fwrite($socket, "\x01\x00");
        if (readExact($socket, 4) !== "\x05\x01\x00\x03") { throw new RuntimeException('hostname not resolved through SOCKS'); }
        $node = readExact($socket, ord(readExact($socket, 1))); readExact($socket, 2);
        fwrite($socket, "\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x50");
        $request = ''; while (!str_contains($request, "\r\n\r\n")) { $line = fgets($socket); if ($line === false) { throw new RuntimeException('request truncated'); } $request .= $line; }
        if (!str_starts_with($request, "POST /onion-service-discovery/v1/candidates/announce HTTP/1.1\r\n") || preg_match('/Content-Length: ([0-9]+)/i', $request, $length) !== 1) { throw new RuntimeException('unexpected request'); }
        if (json_decode(readExact($socket, (int) $length[1]), true) !== ['protocol_version' => 1, 'onion' => $service]) { throw new RuntimeException('announcement carries more than the hint'); }
        $contacted[] = $node;
        fwrite($socket, $responses[$node] ?? throw new RuntimeException('unexpected Node ' . $node)); fclose($socket);
    }
    $output .= (string) stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($error !== '') { throw new RuntimeException('stderr: ' . $error); }
    return [$exit, json_decode($output, true, 32, JSON_THROW_ON_ERROR), $contacted];
}

$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$checks = 0;
$day = AnnouncementSchedule::DAY; $h1 = 'sha256:' . str_repeat('1', 64); $h2 = 'sha256:' . str_repeat('2', 64); $t = 1800000000;
$at = static fn (int $seconds): string => gmdate('Y-m-d\TH:i:s\Z', $seconds);

// Pure schedule rules.
$assert(array_map(AnnouncementSchedule::interval(...), [1, 2, 3, 4, 5, 6, 7, 1000]) === array_map(static fn (int $d): int => $d * $day, [1, 2, 4, 8, 16, 32, 32, 32]), 'backoff intervals'); ++$checks;
$entry = static fn (int $last, string $hash, int $attempts): array => ['at' => $at($last), 'result' => 'HINT_RECEIVED_UNCONFIRMED', 'manifest_hash' => $hash, 'attempts' => $attempts, 'next_at' => $at($last + AnnouncementSchedule::interval($attempts))];
$cases = [
    'initial' => [null, null, $t, true, 'INITIAL'],
    'included' => [null, 'included', $t, false, 'ALREADY_INCLUDED'],
    'contradictory' => [null, 'contradictory', $t, false, 'NODE_EVIDENCE_NEEDS_REVIEW'],
    'rejected' => [$entry($t - 9 * $day, $h1, 1), 'rejected', $t, false, 'NODE_EVIDENCE_NEEDS_REVIEW'],
    'stale-initial' => [null, 'stale', $t, true, 'INITIAL'],
    'daily-limit-same-revision' => [$entry($t - 3600, $h1, 1), 'not_present', $t, false, 'DAILY_LIMIT'],
    'daily-limit-new-revision' => [$entry($t - $day + 1, $h2, 1), null, $t, false, 'DAILY_LIMIT'],
    'new-revision' => [$entry($t - $day, $h2, 5), 'stale', $t, true, 'NEW_REVISION'],
    'backoff' => [$entry($t - 3 * $day, $h1, 3), 'not_present', $t, false, 'BACKOFF'],
    'missing-observation' => [$entry($t - 4 * $day, $h1, 3), 'unavailable', $t, true, 'MISSING_OBSERVATION'],
    'unknown-observation' => [$entry($t - 4 * $day, $h1, 3), null, $t, true, 'MISSING_OBSERVATION'],
    'clock-rollback' => [$entry($t + 10 * $day, $h1, 1), null, $t, false, 'DAILY_LIMIT'],
    'legacy-entry' => [['at' => $at($t - 2 * $day), 'result' => 'TOR_REQUEST_FAILED'], null, $t, true, 'NEW_REVISION'],
];
foreach ($cases as $name => [$saved, $category, $now, $due, $reason]) {
    $decision = AnnouncementSchedule::decide($saved, $category, $h1, $now);
    $assert($decision['due'] === $due && $decision['reason'] === $reason, 'decision ' . $name . ' ' . json_encode($decision)); ++$checks;
}
$assert(AnnouncementSchedule::decide($entry($t - 3 * $day, $h1, 3), 'not_present', $h1, $t)['next_due_at'] === $t + $day, 'backoff next time'); ++$checks;
$next = AnnouncementSchedule::attempt($entry($t - 4 * $day, $h1, 3), $h1, $t, 'TOR_REQUEST_FAILED');
$assert($next['attempts'] === 4 && $next['next_at'] === $at($t + 8 * $day) && $next['manifest_hash'] === $h1, 'attempt counts per revision'); ++$checks;
$assert(AnnouncementSchedule::attempt($entry($t - 4 * $day, $h2, 6), $h1, $t, 'HINT_RECEIVED_UNCONFIRMED')['attempts'] === 1, 'new revision resets the backoff'); ++$checks;

// A never-observing Node over 120 days with the six-hourly timer: 8 attempts, never two within a day.
$sent = []; $saved = null;
for ($now = $t; $now < $t + 120 * $day; $now += 6 * 3600) {
    if (AnnouncementSchedule::decide($saved, 'not_present', $h1, $now)['due']) { $saved = AnnouncementSchedule::attempt($saved, $h1, $now, 'HINT_RECEIVED_UNCONFIRMED'); $sent[] = intdiv($now - $t, $day); }
}
$assert($sent === [0, 1, 3, 7, 15, 31, 63, 95], 'bounded attempt days ' . json_encode($sent)); ++$checks;

$root = sys_get_temp_dir() . '/osd-announcement-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$server = null;
try {
    $fixtures = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
    foreach ($fixtures->cases as $case) { if ($case->id === 'valid-successor') { [$genesis, $current] = array_map($verifier->verify(...), $case->records); } }
    $hash = $current->contentHash; $service = $current->document()->onion;
    $v = json_decode(file_get_contents($project . '/node-php/config/example.json'), true, 32, JSON_THROW_ON_ERROR);
    $v['role'] = 'publisher'; $v['node']['onion'] = $service; $v['features']['publish_service'] = true;
    $v['storage']['path'] = $root . '/must-not-be-created'; $v['export_path'] = $root . '/exports-must-not-be-created';
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); if ($server === false) { throw new RuntimeException('local fixture unavailable'); }
    $v['transport']['proxy_port'] = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1); $v['transport']['timeout_ms'] = 3000;
    $cfg = $root . '/publisher.json'; file_put_contents($cfg, json_encode($v, JSON_THROW_ON_ERROR)); chmod($cfg, 0600);
    file_put_contents($root . '/manifest.json', $current->canonicalWire);
    $nodes = []; foreach (range(1, 4) as $seed) { $nodes[$seed] = OnionAddress::parse(testOnion($seed)); }
    [$a, $b, $c, $d] = [$nodes[1], $nodes[2], $nodes[3], $nodes[4]];

    // Saved per-Node membership: only hashes are stored; a tampered map is dropped as a whole.
    $membership = ['expected_state' => ['manifest_hash' => $hash, 'revision' => 2, 'logical_service_id' => $genesis->document()->service_identity_public_key, 'key_epoch' => 1],
        'selected_sources' => 3, 'distinct_node_keys' => 3, 'included_with_node_direct_observation' => 1,
        'counts' => ['included' => 1, 'stale' => 0, 'not_present' => 1, 'contradictory' => 0, 'unavailable' => 0, 'rejected' => 1],
        'sources' => [['node_onion' => $a->hostname, 'category' => 'included'], ['node_onion' => $b->hostname, 'category' => 'not_present'], ['node_onion' => $c->hostname, 'category' => 'rejected']]];
    PublisherEvidence::recordMembership($membership, $cfg, $t);
    $loaded = PublisherEvidence::load($cfg)['membership'];
    $assert($loaded['nodes'] === [PublisherEvidence::nodeKey($a) => 'included', PublisherEvidence::nodeKey($b) => 'not_present', PublisherEvidence::nodeKey($c) => 'rejected']
        && !str_contains((string) file_get_contents(PublisherEvidence::path($cfg)), $a->hostname), 'per-Node membership saved as hashes'); ++$checks;
    $wire = (string) file_get_contents(PublisherEvidence::path($cfg));
    file_put_contents(PublisherEvidence::path($cfg), str_replace(':"not_present"', ':"included"', $wire, $replaced)); $assert($replaced === 1, 'tamper fixture');
    $assert(!isset(PublisherEvidence::load($cfg)['membership']), 'inconsistent per-Node categories accepted'); ++$checks;
    file_put_contents(PublisherEvidence::path($cfg), $wire);

    // Reservation: A already includes the revision, C needs review, B and D are due once.
    $plan = PublisherEvidence::reserveAnnouncements($cfg, $t, $hash, [$a, $b, $c, $d]);
    $assert(array_map(static fn (array $row): array => [$row['due'], $row['reason'], $row['attempt']], $plan)
        === [[false, 'ALREADY_INCLUDED', null], [true, 'INITIAL', 1], [false, 'NODE_EVIDENCE_NEEDS_REVIEW', null], [true, 'INITIAL', 1]], 'reservation plan ' . json_encode($plan)); ++$checks;
    $saved = PublisherEvidence::load($cfg)['announcement']['nodes'];
    $assert(array_keys($saved) == [PublisherEvidence::nodeKey($b), PublisherEvidence::nodeKey($d)] || array_keys($saved) == [PublisherEvidence::nodeKey($d), PublisherEvidence::nodeKey($b)], 'only due Nodes reserved'); ++$checks;
    $assert($saved[PublisherEvidence::nodeKey($b)]['result'] === AnnouncementSchedule::STARTED && $saved[PublisherEvidence::nodeKey($b)]['next_at'] === $at($t + $day), 'reservation counts as an attempt'); ++$checks;
    $again = PublisherEvidence::reserveAnnouncements($cfg, $t + 60, $hash, [$b, $d]);
    $assert(array_column($again, 'reason') === ['DAILY_LIMIT', 'DAILY_LIMIT'], 'second run within a day'); ++$checks;
    PublisherEvidence::finishAnnouncement($cfg, $t, $b, 'HINT_RECEIVED_UNCONFIRMED');
    PublisherEvidence::finishAnnouncement($cfg, $t + 1, $d, 'HINT_RECEIVED_UNCONFIRMED');
    $saved = PublisherEvidence::load($cfg)['announcement']['nodes'];
    $assert($saved[PublisherEvidence::nodeKey($b)]['result'] === 'HINT_RECEIVED_UNCONFIRMED' && $saved[PublisherEvidence::nodeKey($d)]['result'] === AnnouncementSchedule::STARTED, 'finish only this run\'s reservation'); ++$checks;
    // Legacy two-member entries stay valid; malformed schedule entries drop the section.
    $evidence = json_decode((string) file_get_contents(PublisherEvidence::path($cfg)), true, 8, JSON_THROW_ON_ERROR);
    $evidence['announcement']['nodes'][PublisherEvidence::nodeKey($c)] = ['at' => $at($t), 'result' => 'TOR_REQUEST_FAILED'];
    file_put_contents(PublisherEvidence::path($cfg), json_encode($evidence, JSON_THROW_ON_ERROR));
    $assert(count(PublisherEvidence::load($cfg)['announcement']['nodes']) === 3, 'legacy entry'); ++$checks;
    $evidence['announcement']['nodes'][PublisherEvidence::nodeKey($c)] = ['at' => $at($t), 'result' => 'TOR_REQUEST_FAILED', 'manifest_hash' => $hash, 'attempts' => 0, 'next_at' => $at($t)];
    file_put_contents(PublisherEvidence::path($cfg), json_encode($evidence, JSON_THROW_ON_ERROR));
    $assert(!isset(PublisherEvidence::load($cfg)['announcement']), 'invalid attempt count accepted'); ++$checks;
    unlink(PublisherEvidence::path($cfg));

    // Two concurrent runs at the same instant reserve a Node exactly once.
    $script = 'require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true) . '; echo json_encode(\OnionServiceDiscovery\Configuration\PublisherEvidence::reserveAnnouncements('
        . var_export($cfg, true) . ', ' . $t . ', ' . var_export($hash, true) . ', [\OnionServiceDiscovery\Domain\OnionAddress::parse(' . var_export($a->hostname, true) . ')])[0]["due"]);';
    $runs = [];
    foreach ([1, 2] as $run) { $runs[] = proc_open([PHP_BINARY, '-r', $script], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes[$run]); fclose($pipes[$run][0]); }
    $dues = [];
    foreach ([1, 2] as $run) { $dues[] = trim((string) stream_get_contents($pipes[$run][1])); fclose($pipes[$run][1]); fclose($pipes[$run][2]); proc_close($runs[$run - 1]); }
    sort($dues);
    $assert($dues === ['false', 'true'], 'concurrent reservation ' . json_encode($dues)); ++$checks;
    unlink(PublisherEvidence::path($cfg));

    // CLI: automatic hints need this exact Manifest verified through the own Onion within a day.
    $hint = '{"status":"HINT_RECEIVED_UNCONFIRMED"}';
    $accepted = "HTTP/1.1 202 Accepted\r\nContent-Type: application/json\r\nContent-Length: " . strlen($hint) . "\r\n\r\n" . $hint;
    $limited = "HTTP/1.1 429 Too Many Requests\r\nRetry-After: 3600\r\nContent-Type: application/json\r\nContent-Length: 2\r\n\r\n{}";
    $nodeKey = static fn (int $seed): string => 'ed25519:' . rtrim(strtr(base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(str_repeat(chr(100 + $seed), 32)))), '+/', '-_'), '=');
    $triples = ['--node', $a->hostname, $nodeKey(1), '--node', $b->hostname, $nodeKey(2)];
    $command = ['network:announce', $cfg, $root . '/manifest.json', ...$triples, '--if-due'];
    [$exit, $out, $contacted] = announce($command, $server, [], $service);
    $assert($exit === 6 && $out['status'] === 'SELF_VERIFICATION_REQUIRED' && $contacted === [] && !is_file(PublisherEvidence::path($cfg)), 'missing self-verification ' . $exit . json_encode($out)); ++$checks;
    PublisherEvidence::recordSelfVerification($cfg, time() - 2 * $day, $hash, 'SIGNATURE_AND_BINDING_VALID');
    [$exit, $out, $contacted] = announce($command, $server, [], $service);
    $assert($exit === 6 && $out['status'] === 'SELF_VERIFICATION_REQUIRED' && $contacted === [], 'stale self-verification'); ++$checks;
    PublisherEvidence::recordSelfVerification($cfg, time(), $genesis->contentHash, 'SIGNATURE_AND_BINDING_VALID');
    [$exit, $out, $contacted] = announce($command, $server, [], $service);
    $assert($exit === 6 && $out['status'] === 'SELF_VERIFICATION_REQUIRED' && $contacted === [], 'self-verification of another revision'); ++$checks;
    PublisherEvidence::recordSelfVerification($cfg, time(), $hash, 'SIGNATURE_AND_BINDING_VALID');
    [$exit, $out, $contacted] = announce($command, $server, [$a->hostname => $accepted, $b->hostname => $limited], $service);
    $assert($exit === 6 && $out['status'] === 'SOME_HINTS_FAILED' && $out['mode'] === 'BOUNDED_AUTOMATIC' && $contacted === [$a->hostname, $b->hostname]
        && $out['counts'] === ['sent' => 2, 'accepted' => 1, 'rate_limited' => 1, 'unreachable' => 0, 'other_failure' => 0, 'skipped' => 0]
        && array_column($out['nodes'], 'result') === ['HINT_RECEIVED_UNCONFIRMED', 'ANNOUNCEMENT_RATE_LIMITED'] && array_column($out['nodes'], 'reason') === ['INITIAL', 'INITIAL']
        && $out['direct_observation'] === 'NOT_CONFIRMED' && $out['index_membership'] === 'NOT_CONFIRMED' && $out['state_mutated'] === true, 'first automatic run ' . json_encode($out)); ++$checks;
    $saved = PublisherEvidence::load($cfg)['announcement']['nodes'];
    $assert($saved[PublisherEvidence::nodeKey($a)]['result'] === 'HINT_RECEIVED_UNCONFIRMED' && $saved[PublisherEvidence::nodeKey($b)]['result'] === 'ANNOUNCEMENT_RATE_LIMITED'
        && $saved[PublisherEvidence::nodeKey($b)]['manifest_hash'] === $hash && $saved[PublisherEvidence::nodeKey($b)]['attempts'] === 1, 'saved automatic results'); ++$checks;
    [$exit, $out, $contacted] = announce($command, $server, [], $service);
    $assert($exit === 0 && $out['status'] === 'NOTHING_DUE' && $contacted === [] && array_column($out['nodes'], 'reason') === ['DAILY_LIMIT', 'DAILY_LIMIT']
        && $out['network_side_effects'] === false && $out['state_mutated'] === false, 'rerun within a day'); ++$checks;
    // Explicit operator action is not limited by the schedule but saves only with --save-status.
    $before = (string) file_get_contents(PublisherEvidence::path($cfg));
    [$exit, $out, $contacted] = announce(['network:announce', $cfg, $root . '/manifest.json', ...$triples], $server, [$a->hostname => $accepted, $b->hostname => $accepted], $service);
    $assert($exit === 0 && $out['status'] === 'HINTS_RECEIVED_UNCONFIRMED' && $out['mode'] === 'EXPLICIT' && $contacted === [$a->hostname, $b->hostname]
        && $out['state_mutated'] === false && file_get_contents(PublisherEvidence::path($cfg)) === $before, 'explicit multi-Node announcement'); ++$checks;
    // Without Node arguments the command announces to the publisher_nodes of the configuration.
    $configured = json_decode((string) file_get_contents($cfg), true, 32, JSON_THROW_ON_ERROR);
    $configured['publisher_nodes'] = [['onion' => $a->hostname, 'expected_node_key' => $nodeKey(1)], ['onion' => $b->hostname, 'expected_node_key' => $nodeKey(2)]];
    file_put_contents($root . '/configured.json', json_encode($configured, JSON_THROW_ON_ERROR)); chmod($root . '/configured.json', 0600);
    [$exit, $out, $contacted] = announce(['network:announce', $root . '/configured.json', $root . '/manifest.json'], $server, [$a->hostname => $accepted, $b->hostname => $accepted], $service);
    $assert($exit === 0 && $out['status'] === 'HINTS_RECEIVED_UNCONFIRMED' && $contacted === [$a->hostname, $b->hostname], 'configured Nodes announced'); ++$checks;
    unlink($root . '/configured.json');
    [$exit, $out, $contacted] = announce(['service:announce', $cfg, $root . '/manifest.json', $a->hostname], $server, [$a->hostname => $limited], $service);
    $assert($exit === 5 && $out['error'] === 'ANNOUNCEMENT_RATE_LIMITED' && $contacted === [$a->hostname], 'single Node form unchanged'); ++$checks;
    foreach ([[[$a->hostname, '--if-due'], 2, 'USAGE_ERROR'], [['--peer', $a->hostname, $nodeKey(1)], 2, 'USAGE_ERROR'],
        [['--node', $a->hostname, $nodeKey(1), '--node', $a->hostname, $nodeKey(2)], 3, 'SOURCE_DUPLICATE'],
        [['--node', $a->hostname, 'ed25519:short', '--if-due'], 3, 'PUBLIC_KEY_INVALID']] as [$extra, $code, $error]) {
        [$exit, $out, $contacted] = announce(['network:announce', $cfg, $root . '/manifest.json', ...$extra], $server, [], $service);
        $assert($exit === $code && $out['error'] === $error && $contacted === [], 'usage ' . $error . ' ' . json_encode($out)); ++$checks;
    }
    $assert(!file_exists($root . '/must-not-be-created') && !file_exists($root . '/exports-must-not-be-created'), 'publisher storage created'); ++$checks;

    // The timer template announces last, after the evidence the schedule depends on.
    $unit = (string) file_get_contents($project . '/deployment/systemd/osd-publisher-check.service');
    preg_match_all('/^ExecStart=-\S+ \S+ (\S+) /m', $unit, $steps);
    $assert($steps[1] === ['service:verify', 'network:discoverability-check', 'network:announce'] && str_contains($unit, '--if-due') && str_contains($unit, 'onion-service-discovery-history.json'), 'publisher timer steps ' . json_encode($steps[1])); ++$checks;
} finally {
    if (is_resource($server)) { fclose($server); }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo "PASS: bounded automatic announcement {$checks} controlled checks (not live Tor evidence)\n";
