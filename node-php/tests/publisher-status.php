<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Configuration\PublisherStatus;
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
