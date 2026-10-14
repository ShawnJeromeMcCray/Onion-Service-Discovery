<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Configuration\PublisherStatus;
use OnionServiceDiscovery\Configuration\PublisherEvidence;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Configuration\Diagnostics;
use OnionServiceDiscovery\Configuration\PublisherInitializer;
use OnionServiceDiscovery\Http\StatusPage;
use OnionServiceDiscovery\Protocol\RecordVerifier;

$root = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/publisher-status-' . bin2hex(random_bytes(12));
mkdir($temp, 0700);
$fixture = json_decode(file_get_contents($root . '/protocol/test-vectors/signed-records.json'), true, 32, JSON_THROW_ON_ERROR);
$v = json_decode(file_get_contents($root . '/node-php/config/example.json'), true, 32, JSON_THROW_ON_ERROR);
$v['role'] = 'publisher'; $v['node']['onion'] = $fixture['stories']['genesis']['onion']; $v['features']['publish_service'] = true;
$v['storage']['path'] = $temp . '/must-not-be-created'; $v['export_path'] = $temp . '/exports-must-not-be-created';
file_put_contents($temp . '/config.json', json_encode($v, JSON_THROW_ON_ERROR));
$config = NodeConfig::load($temp . '/config.json');
$verifier = new RecordVerifier($root . '/protocol/schemas');
$manifest = $temp . '/manifest.json';
$assert = static function (bool $ok): void { if (!$ok) { throw new RuntimeException('Publisher status assertion failed'); } };
$checks = 0;
try {
    $assert(PublisherStatus::inspect($config, $verifier, $manifest)['overall'] === 'ERROR'); ++$checks;
    file_put_contents($manifest, json_encode($fixture['stories']['genesis'], JSON_THROW_ON_ERROR));
    $facts = PublisherStatus::inspect($config, $verifier, $manifest);
    $assert($facts['overall'] === 'READY_LOCALLY' && $facts['sampled_discoverability'] === 'UNCONFIRMED'); ++$checks;
    $assert($facts['active_index_membership'] === 'NOT_CHECKED'); ++$checks;
    // Saved aggregate evidence drives the dashboard only for the exact installed revision.
    $cfg = $temp . '/config.json'; $now = 1800000000; $hash = $facts['manifest']['hash'];
    $genesisKey = $facts['manifest']['current_key'];
    $result = static fn (array $counts, int $observed, ?string $h = null): array => ['expected_state' => ['manifest_hash' => $h ?? $hash, 'revision' => 1, 'logical_service_id' => $genesisKey, 'key_epoch' => 1],
        'selected_sources' => array_sum($counts), 'distinct_node_keys' => array_sum($counts), 'included_with_node_direct_observation' => $observed,
        'counts' => $counts + ['included' => 0, 'stale' => 0, 'not_present' => 0, 'contradictory' => 0, 'unavailable' => 0, 'rejected' => 0]];
    chmod($cfg, 0600);
    $assert($facts['answer'] === 'NOT YET' && $facts['pipeline']['onion_self_verification'] === 'PENDING' && $facts['pipeline']['manifest'] === 'PASS'); ++$checks;
    PublisherEvidence::recordMembership($result(['included' => 2, 'unavailable' => 1], 2), $cfg, $now); $saved = PublisherEvidence::path($cfg);
    $assert($saved === $temp . '/config.discoverability.json' && (fileperms($saved) & 0077) === 0); ++$checks;
    $found = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now + 60);
    $assert($found['overall'] === 'DISCOVERABLE' && $found['external_observation'] === 'OBSERVED_CURRENT_REVISION' && str_contains($found['sampled_discoverability'], '2 of 3')
        && $found['active_index_membership'] === 'CURRENT_REVISION_IN_2_OF_3_CHECKED_NODE_SNAPSHOTS'); ++$checks;
    $assert($found['answer'] === 'YES' && $found['manifest']['logical_service_id'] === $genesisKey && $found['manifest']['key_epoch'] === 1
        && $found['pipeline']['searchable_state'] === 'PASS' && $found['pipeline']['redundancy_freshness'] === 'PASS' && $found['pipeline']['bootstrap_connectivity'] === 'PASS'); ++$checks;
    // Self-verification and announcement evidence; Node addresses are stored only as hashes.
    $nodeA = OnionAddress::parse('pg6mmjiyjmcrsslvykfwnntlaru7p5svn6y2ymmju6nubxndf4pscryd.onion');
    PublisherEvidence::recordSelfVerification($cfg, $now, $hash, 'SIGNATURE_AND_BINDING_VALID');
    PublisherEvidence::recordAnnouncement($cfg, $now, $nodeA, 'HINT_RECEIVED_UNCONFIRMED');
    PublisherEvidence::recordAnnouncement($cfg, $now + 1, OnionAddress::parse($v['node']['onion']), 'TOR_REQUEST_FAILED');
    $assert(!str_contains((string) file_get_contents($saved), $nodeA->hostname)); ++$checks;
    $found = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now + 60);
    $assert($found['self_verification'] === 'PASS' && $found['announcement'] === 'HINT_ACCEPTED_BY_1_OF_2_ATTEMPTED_NODES' && $found['pipeline']['announcement'] === 'PASS'
        && $found['pipeline']['onion_self_verification'] === 'PASS' && $found['overall'] === 'DISCOVERABLE'); ++$checks;
    PublisherEvidence::recordSelfVerification($cfg, $now, null, 'TOR_REQUEST_FAILED');
    $failed = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($failed['self_verification'] === 'FAIL_TOR_REQUEST_FAILED' && $failed['pipeline']['onion_self_verification'] === 'FAIL'); ++$checks;
    PublisherEvidence::recordSelfVerification($cfg, $now, $hash, 'SIGNATURE_AND_BINDING_VALID');
    $page = StatusPage::render($found)['body'];
    $assert(str_contains($page, 'DISCOVERABLE') && str_contains($page, '<strong>YES</strong>') && str_contains($page, '2 of 3 checked Discovery Nodes') && str_contains($page, 'Onion self verification')
        && !str_contains($page, $nodeA->hostname) && !str_contains($page, $temp)); ++$checks;
    $old = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now + PublisherStatus::EVIDENCE_DEGRADED_AFTER_SECONDS + 1);
    $assert($old['overall'] === 'DISCOVERABLE_DEGRADED' && $old['answer'] === 'DEGRADED' && $old['pipeline']['redundancy_freshness'] === 'WARN'); ++$checks;
    PublisherEvidence::recordMembership($result(['included' => 1], 0), $cfg, $now);
    $unobserved = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($unobserved['overall'] === 'READY_LOCALLY' && $unobserved['external_observation'] === 'NO_EXTERNAL_OBSERVATION'); ++$checks;
    PublisherEvidence::recordMembership($result(['included' => 1, 'contradictory' => 1], 1), $cfg, $now);
    $conflict = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($conflict['overall'] === 'IDENTITY_CONFLICT' && $conflict['answer'] === 'PROBLEM' && $conflict['pipeline']['external_direct_observation'] === 'FAIL'); ++$checks;
    PublisherEvidence::recordMembership($result(['stale' => 2], 0), $cfg, $now);
    $pending = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($pending['overall'] === 'READY_LOCALLY' && $pending['external_observation'] === 'OBSERVED_STALE_REVISION'); ++$checks;
    PublisherEvidence::recordMembership($result(['included' => 3], 3, 'sha256:' . str_repeat('0', 64)), $cfg, $now);
    $other = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($other['overall'] === 'READY_LOCALLY' && $other['active_index_membership'] === 'NOT_CHECKED'); ++$checks;
    file_put_contents($saved, '{"evidence_version":1,"membership":{"included":99}}');
    $assert(PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now)['overall'] === 'READY_LOCALLY'); ++$checks;
    unlink($saved);
    $page = StatusPage::render($facts);
    $assert($page['status'] === 200 && $page['headers']['Cache-Control'] === 'no-store' && str_contains($page['headers']['Content-Security-Policy'], 'frame-ancestors')); ++$checks;
    $assert(!str_contains($page['body'], $temp) && !str_contains($page['body'], 'must-not-be-created')); ++$checks;
    $assert(StatusPage::render($facts, 'HEAD')['body'] === '' && StatusPage::render($facts, 'POST')['status'] === 405); ++$checks;
    $facts['next_action'] = '<script>alert(1)</script>'; $facts['private_path'] = $temp;
    $html = StatusPage::render($facts)['body'];
    $assert(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;') && !str_contains($html, $temp)); ++$checks;
    file_put_contents($manifest, json_encode($fixture['stories']['successor'], JSON_THROW_ON_ERROR));
    $later = PublisherStatus::inspect($config, $verifier, $manifest);
    $assert($later['manifest']['continuity'] === 'NOT_CHECKED' && !isset($later['manifest']['genesis_anchor'])); ++$checks;
    $broken = $fixture['stories']['genesis']; $broken['service']['name'] = 'tampered';
    file_put_contents($manifest, json_encode($broken, JSON_THROW_ON_ERROR));
    $assert(PublisherStatus::inspect($config, $verifier, $manifest)['overall'] === 'ERROR'); ++$checks;
    $health = Diagnostics::inspect($config, $verifier);
    $assert($health['status'] === 'PASS' && !isset($health['security_check_summary']['storage_integrity'])); ++$checks;
    $assert(!file_exists($v['storage']['path']) && !file_exists($v['export_path'])); ++$checks;
    $created = PublisherInitializer::create($temp . '/initialized.json', \OnionServiceDiscovery\Domain\OnionAddress::parse($v['node']['onion']));
    $assert(!$created->features['publish_service'] && $created->nodeKeyPath === null && (fileperms($temp . '/initialized.json') & 0077) === 0); ++$checks;
    try { PublisherInitializer::create($temp . '/initialized.json', \OnionServiceDiscovery\Domain\OnionAddress::parse($v['node']['onion']), true); throw new RuntimeException('Configuration overwritten'); }
    catch (InvalidArgumentException $error) { $assert($error->getMessage() === 'CONFIGURATION_CREATE_REFUSED'); ++$checks; }
    $assert(!file_exists($created->storagePath) && !file_exists($created->exportPath)); ++$checks;
    foreach (['mirror_directory','discover_services','sync_with_peers'] as $flag) {
        $bad = $v; $bad['features'][$flag] = true; file_put_contents($temp . '/config.json', json_encode($bad));
        try { NodeConfig::load($temp . '/config.json'); throw new RuntimeException('Publisher accepted Node role'); }
        catch (InvalidArgumentException) { ++$checks; }
    }
} finally { foreach (glob($temp . '/*') as $path) { unlink($path); } rmdir($temp); }
echo 'PASS: publisher read-only status ' . $checks . " checks\n";
