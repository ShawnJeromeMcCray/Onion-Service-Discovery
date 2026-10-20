<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/health-report.inc.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Configuration\PublisherStatus;
use OnionServiceDiscovery\Configuration\PublisherEvidence;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Configuration\Diagnostics;
use OnionServiceDiscovery\Configuration\PublisherInitializer;
use OnionServiceDiscovery\Http\StatusPage;
use OnionServiceDiscovery\Protocol\RecordVerifier;

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
    $assert($facts['manifest']['current_key_fingerprint'] === \OnionServiceDiscovery\Crypto\KeyFingerprint::of($genesisKey)
        && str_contains(StatusPage::render($facts)['body'], $facts['manifest']['current_key_fingerprint']), 'Service key fingerprint (D-025)'); ++$checks;
    // Synthetic membership results: Node n (seed n) gets the n-th category in the order given.
    $result = static function (array $counts, int $observed, ?string $h = null) use ($hash, $genesisKey): array {
        $sources = []; $seed = 1;
        foreach ($counts as $category => $count) { for ($i = 0; $i < $count; ++$i) { $sources[] = ['node_onion' => testOnion($seed++), 'category' => $category]; } }
        return ['expected_state' => ['manifest_hash' => $h ?? $hash, 'revision' => 1, 'logical_service_id' => $genesisKey, 'key_epoch' => 1],
            'selected_sources' => array_sum($counts), 'distinct_node_keys' => array_sum($counts), 'included_with_node_direct_observation' => $observed,
            'counts' => $counts + ['included' => 0, 'stale' => 0, 'not_present' => 0, 'contradictory' => 0, 'unavailable' => 0, 'rejected' => 0], 'sources' => $sources];
    };
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
    PublisherEvidence::recordAnnouncement($cfg, $now, $nodeA, 'HINT_RECEIVED_UNCONFIRMED', $hash);
    PublisherEvidence::recordAnnouncement($cfg, $now + 1, OnionAddress::parse($v['node']['onion']), 'TOR_REQUEST_FAILED', $hash);
    $assert(!str_contains((string) file_get_contents($saved), $nodeA->hostname)); ++$checks;
    $found = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now + 60);
    $assert($found['self_verification'] === 'PASS' && $found['announcement'] === 'HINT_ACCEPTED_BY_1_OF_2_ATTEMPTED_NODES' && $found['pipeline']['announcement'] === 'PASS'
        && $found['pipeline']['onion_self_verification'] === 'PASS' && $found['overall'] === 'DISCOVERABLE'); ++$checks;
    // Spec 47 section 23: result counts and the next bounded retry for Nodes without the current revision.
    $assert($found['announcement_results'] === 'accepted 1, rate limited 0, not accepting announcements 0, unreachable 1, local Tor unavailable 0, in progress 0, other failures 0'
        && $found['announcement_next_retry_at'] === gmdate('Y-m-d\TH:i:s\Z', $now + 86400)); ++$checks;
    PublisherEvidence::recordSelfVerification($cfg, $now, null, 'TOR_REQUEST_FAILED');
    $failed = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($failed['self_verification'] === 'FAIL_TOR_REQUEST_FAILED' && $failed['pipeline']['onion_self_verification'] === 'FAIL'); ++$checks;
    PublisherEvidence::recordSelfVerification($cfg, $now, $hash, 'SIGNATURE_AND_BINDING_VALID');
    $page = StatusPage::render($found)['body'];
    $assert(str_contains($page, 'DISCOVERABLE') && str_contains($page, '<strong>YES</strong>') && str_contains($page, '2 of 3 checked Discovery Nodes') && str_contains($page, 'Onion self verification')
        && str_contains($page, 'announcement next retry at') && str_contains($page, 'unreachable 1')
        && !str_contains($page, $nodeA->hostname) && !str_contains($page, $temp)); ++$checks;
    $old = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now + PublisherStatus::EVIDENCE_DEGRADED_AFTER_SECONDS + 1);
    $assert($old['overall'] === 'DISCOVERABLE_DEGRADED' && $old['answer'] === 'DEGRADED' && $old['pipeline']['redundancy_freshness'] === 'WARN'); ++$checks;
    PublisherEvidence::recordMembership($result(['included' => 1], 0), $cfg, $now);
    $unobserved = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($unobserved['overall'] === 'EXTERNALLY_OBSERVED' && $unobserved['answer'] === 'NOT YET' && $unobserved['external_observation'] === 'NO_EXTERNAL_OBSERVATION'); ++$checks;
    PublisherEvidence::recordMembership($result(['included' => 1, 'contradictory' => 1], 1), $cfg, $now);
    $conflict = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($conflict['overall'] === 'IDENTITY_CONFLICT' && $conflict['answer'] === 'PROBLEM' && $conflict['pipeline']['external_direct_observation'] === 'FAIL'); ++$checks;
    PublisherEvidence::recordMembership($result(['stale' => 2], 0), $cfg, $now);
    $pending = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($pending['overall'] === 'EXTERNALLY_OBSERVED' && $pending['external_observation'] === 'OBSERVED_STALE_REVISION'); ++$checks;
    // D-036: Nodes that include the revision but serve a signed index older than seven days are not counted.
    $outdated = $result(['included' => 2, 'unavailable' => 1], 0); $outdated['included_with_outdated_checkpoint'] = 2;
    PublisherEvidence::recordMembership($outdated, $cfg, $now);
    $old = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($old['overall'] === 'EXTERNALLY_OBSERVED' && $old['index_freshness'] === '2_INCLUDING_NODES_OLDER_THAN_7_DAYS' && str_contains($old['next_action'], 'older than seven days')
        && count($old['warnings']) === 1 && str_contains(StatusPage::render($old)['body'], 'not counted as discoverable')); ++$checks;
    $mixed = $result(['included' => 2], 1); $mixed['included_with_outdated_checkpoint'] = 1;
    PublisherEvidence::recordMembership($mixed, $cfg, $now);
    $one = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($one['overall'] === 'DISCOVERABLE' && $one['index_freshness'] === '1_INCLUDING_NODES_OLDER_THAN_7_DAYS' && count($one['warnings']) === 1); ++$checks;
    $tampered = json_decode((string) file_get_contents(PublisherEvidence::path($cfg)), true); $tampered['membership']['outdated_checkpoints'] = 2;
    file_put_contents(PublisherEvidence::path($cfg), json_encode($tampered));
    $assert(PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now)['overall'] !== 'DISCOVERABLE', 'inconsistent freshness counts are refused'); ++$checks;
    PublisherEvidence::recordMembership($result(['included' => 3], 3, 'sha256:' . str_repeat('0', 64)), $cfg, $now);
    $other = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    // Membership for another revision is ignored; the accepted announcement of this revision still counts.
    $assert($other['overall'] === 'ANNOUNCED_PENDING_VERIFICATION' && $other['active_index_membership'] === 'NOT_CHECKED' && str_contains($other['next_action'], 'revision 1, not the installed')); ++$checks;
    file_put_contents($saved, '{"evidence_version":1,"membership":{"included":99}}');
    $assert(PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now)['overall'] === 'READY_LOCALLY'); ++$checks;
    unlink($saved);
    // Spec 47 sections 9 and 62: each primary state on the way to DISCOVERABLE, derived from saved evidence.
    PublisherEvidence::recordSelfVerification($cfg, $now, $hash, 'SIGNATURE_AND_BINDING_VALID');
    $connecting = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($connecting['overall'] === 'CONNECTING' && $connecting['answer'] === 'NOT YET' && str_contains($connecting['next_action'], 'announce it')
        && $connecting['pipeline']['onion_self_verification'] === 'PASS' && $connecting['pipeline']['bootstrap_connectivity'] === 'PENDING'); ++$checks;
    PublisherEvidence::recordAnnouncement($cfg, $now, $nodeA, 'HINT_RECEIVED_UNCONFIRMED', $hash);
    $announced = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($announced['overall'] === 'ANNOUNCED_PENDING_VERIFICATION' && $announced['answer'] === 'NOT YET' && str_contains($announced['next_action'], 'accepted the announcement')
        && $announced['bootstrap'] === 'ANNOUNCEMENT_ACCEPTED_BY_1_NODES' && $announced['pipeline']['bootstrap_connectivity'] === 'PASS'); ++$checks;
    $outdated = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now + PublisherStatus::EVIDENCE_DEGRADED_AFTER_SECONDS + 1);
    $assert($outdated['self_verification'] === 'PASS_OUTDATED' && $outdated['pipeline']['onion_self_verification'] === 'WARN' && str_contains($outdated['next_action'], 'older than seven days')); ++$checks;
    unlink($saved);
    // No checked Node reachable and nothing announced: bootstrap fails, with a remedy.
    PublisherEvidence::recordMembership($result(['unavailable' => 3], 0), $cfg, $now);
    $offline = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($offline['overall'] === 'READY_LOCALLY' && $offline['bootstrap'] === 'NO_CHECKED_NODE_REACHABLE' && $offline['pipeline']['bootstrap_connectivity'] === 'FAIL'
        && str_contains($offline['next_action'], 'None of the checked Nodes answered')); ++$checks;
    // Discoverable on exactly one Node: found, without redundancy.
    PublisherEvidence::recordMembership($result(['included' => 1], 1), $cfg, $now);
    $single = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($single['overall'] === 'DISCOVERABLE' && str_contains($single['sampled_discoverability'], '1 of 1') && $single['pipeline']['redundancy_freshness'] === 'WARN'); ++$checks;
    // A failing self-check outranks good news in the next action and names its remedy.
    PublisherEvidence::recordSelfVerification($cfg, $now, null, 'HTTP_NOT_FOUND');
    $routing = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $assert($routing['overall'] === 'DISCOVERABLE' && str_contains($routing['next_action'], 'answered 404') && $routing['pipeline']['onion_self_verification'] === 'FAIL'); ++$checks;
    PublisherEvidence::recordSelfVerification($cfg, $now, null, 'TOR_PROXY_UNREACHABLE');
    $assert(str_contains(PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now)['next_action'], 'SOCKS listener')); ++$checks;
    // A check from before the installed revision says nothing about it; a later one that differs is a routing problem.
    PublisherEvidence::recordSelfVerification($cfg, $now, 'sha256:' . str_repeat('1', 64), 'SIGNATURE_AND_BINDING_VALID');
    touch($manifest, $now + 10);
    $since = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now + 20);
    $assert($since['self_verification'] === 'NOT_CHECKED_SINCE_INSTALL' && $since['pipeline']['onion_self_verification'] === 'PENDING'); ++$checks;
    touch($manifest, $now - 10);
    $differs = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now + 20);
    $assert($differs['self_verification'] === 'SERVED_MANIFEST_DIFFERS' && $differs['pipeline']['onion_self_verification'] === 'FAIL'); ++$checks;
    unlink($saved);
    // D-052: a Node that contradicted its previous checkpoint is named separately (spec 47 section 79).
    $integrity = $result(['included' => 1, 'rejected' => 1], 1);
    $integrity['sources'][1]['error'] = 'SOURCE_CHECKPOINT_EQUIVOCATION';
    PublisherEvidence::recordMembership($integrity, $cfg, $now);
    $warned = PublisherStatus::inspect($config, $verifier, $manifest, $cfg, $now);
    $warnedPage = StatusPage::render($warned)['body'];
    $assert($warned['source_integrity'] === 'CHECKPOINT_CONFLICT_AT_1_CHECKED_NODES' && count($warned['warnings']) === 1 && $warned['overall'] === 'DISCOVERABLE'
        && str_contains($warnedPage, 'role="alert"') && str_contains($warnedPage, 'Conflicting signed index checkpoints')); ++$checks;
    $assert(str_contains($warnedPage, 'href="/.well-known/onion-service-discovery.json"')); ++$checks;
    // doctor and the support bundle report the publication too (spec 32 sections 8 and 10).
    $doctor = Diagnostics::inspect($config, $verifier, $manifest, $cfg); assertHealthReport($doctor);
    $assert($doctor['manifest_state'] === 'SIGNATURE_VALID' && $doctor['security_check_summary']['publisher_manifest'] === 'PASS' && $doctor['publication']['overall'] === 'DISCOVERABLE'
        && in_array('PUBLISHER_CHECKED_NODE_CHECKPOINT_CONFLICT', $doctor['warnings'], true)); ++$checks;
    $bundle = json_encode(\OnionServiceDiscovery\Configuration\SupportBundle::create($config, $verifier, $cfg, $manifest), JSON_THROW_ON_ERROR);
    $assert(str_contains($bundle, '"manifest_state":"SIGNATURE_VALID"') && !str_contains($bundle, $genesisKey) && !str_contains($bundle, (string) $v['node']['onion'])); ++$checks;
    unlink($saved);
    // Publishing switched off in the configuration.
    $off = $v; $off['features']['publish_service'] = false; file_put_contents($temp . '/off.json', json_encode($off, JSON_THROW_ON_ERROR)); chmod($temp . '/off.json', 0600);
    $disabled = PublisherStatus::inspect(NodeConfig::load($temp . '/off.json'), $verifier, $manifest);
    $assert($disabled['overall'] === 'CONFIGURED_NOT_PUBLISHED' && $disabled['answer'] === 'NO' && str_contains($disabled['next_action'], 'Publishing is disabled')); ++$checks;
    // After an Onion migration the new Manifest belongs to the new address (spec 47 section 59).
    file_put_contents($manifest, json_encode($fixture['stories']['migrated'], JSON_THROW_ON_ERROR));
    $migrated = PublisherStatus::inspect($config, $verifier, $manifest);
    $assert($migrated['overall'] === 'ERROR' && $migrated['manifest']['state'] === 'OTHER_ONION' && $migrated['manifest']['onion'] === $fixture['stories']['migrated']['onion']
        && str_contains($migrated['next_action'], 'set node.onion to the new address') && $migrated['pipeline']['manifest'] === 'FAIL'); ++$checks;
    $assert(Diagnostics::inspect($config, $verifier, $manifest, $cfg)['security_check_summary']['publisher_manifest'] === 'FAIL'); ++$checks;
    file_put_contents($manifest, json_encode($fixture['stories']['genesis'], JSON_THROW_ON_ERROR));
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
    $health = Diagnostics::inspect($config, $verifier); assertHealthReport($health);
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
