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
foreach (['server-search', 'unknown', 'flag', 'role', 'path', 'proxy', 'source', 'version'] as $case) {
    $v = $original;
    match ($case) {
        'server-search' => $v['features']['server_search'] = true,
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
$alias = sys_get_temp_dir() . '/osd-public-alias-' . bin2hex(random_bytes(12)); symlink($root . '/node-php/public', $alias);
foreach ([$root . '/node-php/public/index.php', $alias . '/index.php'] as $publicConfig) {
    try { NodeConfig::load($publicConfig); throw new RuntimeException('public configuration path accepted'); }
    catch (InvalidArgumentException $error) { if ($error->getMessage() !== 'PRIVATE_PATH_IN_WEBROOT') { throw $error; } ++$checks; }
}
$v = $original; $v['storage']['path'] = $alias . '/would-be-private.sqlite'; file_put_contents($path, json_encode($v));
try { NodeConfig::load($path); throw new RuntimeException('symlink parent exposed private storage'); }
catch (InvalidArgumentException $error) { if ($error->getMessage() !== 'PRIVATE_PATH_IN_WEBROOT') { throw $error; } ++$checks; }
unlink($alias);
unlink($path); echo 'PASS: strict configuration ' . $checks . " checks\n";
