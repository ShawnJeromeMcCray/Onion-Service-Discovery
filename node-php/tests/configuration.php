<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
$root = dirname(__DIR__, 2);
$path = sys_get_temp_dir() . '/osd-config-' . bin2hex(random_bytes(12)) . '.json';
$original = json_decode(file_get_contents($root . '/node-php/config/example.json'), true, 32, JSON_THROW_ON_ERROR);
file_put_contents($path, json_encode($original, JSON_THROW_ON_ERROR));
if (NodeConfig::load($path)->role !== 'client-host') { throw new RuntimeException('role'); }
$checks = 1;
foreach (['unknown', 'flag', 'role', 'path', 'proxy', 'source', 'version'] as $case) {
    $v = $original;
    match ($case) {
        'unknown' => $v['extra'] = true,
        'flag' => $v['features']['discover_services'] = 'true',
        'role' => $v['features']['discover_services'] = true,
        'path' => $v['storage']['path'] = '/var/lib/../secrets',
        'proxy' => $v['transport']['proxy_host'] = 'example.org',
        'source' => $v['sources'] = ['https://example.org/'],
        'version' => $v['configuration_version'] = 2,
    };
    file_put_contents($path, json_encode($v, JSON_THROW_ON_ERROR));
    try { NodeConfig::load($path); throw new RuntimeException('accepted ' . $case); } catch (InvalidArgumentException) { ++$checks; }
}
unlink($path); echo 'PASS: strict configuration ' . $checks . " checks\n";
