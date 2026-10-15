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
// Refusals name the key path and the rule, never the value (spec 32 section 12).
foreach ([
    'unknown' => [static function (array &$v): void { $v['extra'] = true; }, 'CONFIGURATION_INVALID', 'extra: unknown key'],
    'missing' => [static function (array &$v): void { unset($v['transport']['timeout_ms']); }, 'CONFIGURATION_INVALID', 'transport.timeout_ms: missing'],
    'timeout' => [static function (array &$v): void { $v['transport']['timeout_ms'] = 60001; }, 'CONFIGURATION_INVALID', 'transport.timeout_ms: must be an integer from 100 to 60000'],
    'flag' => [static function (array &$v): void { $v['features']['server_search'] = 'yes'; }, 'CONFIGURATION_INVALID', 'features.server_search: must be true or false'],
    'path' => [static function (array &$v): void { $v['export_path'] = 'relative/exports'; }, 'CONFIGURATION_PATH_INVALID', 'export_path: must be an absolute path of at most 1024 bytes without . or .. segments'],
    'source' => [static function (array &$v): void { $v['sources'] = ['example.onion']; }, 'ADDRESS_INVALID', 'sources[0]: must be a canonical v3 onion address (56 lowercase characters and .onion)'],
    'role' => [static function (array &$v): void { $v['features']['server_search'] = true; }, 'ROLE_CONFIGURATION_INVALID', 'features.server_search: needs the node or combined role'],
] as $case => [$change, $code, $detail]) {
    $v = $original; $change($v); file_put_contents($path, json_encode($v, JSON_THROW_ON_ERROR));
    try { NodeConfig::load($path); throw new RuntimeException('accepted ' . $case); }
    catch (\OnionServiceDiscovery\Protocol\ProtocolException $error) { if ($error->errorCode !== $code || $error->detail !== $detail) { throw new RuntimeException($case . ': ' . $error->errorCode . ' ' . $error->detail); } ++$checks; }
}
// visitor_sources: absent means off; only Client Host/combined roles may enable it; strictly boolean.
file_put_contents($path, json_encode($original, JSON_THROW_ON_ERROR));
if (NodeConfig::load($path)->features['visitor_sources'] !== false) { throw new RuntimeException('visitor sources default'); }
$v = $original; $v['features']['visitor_sources'] = true; file_put_contents($path, json_encode($v, JSON_THROW_ON_ERROR));
if (NodeConfig::load($path)->features['visitor_sources'] !== true) { throw new RuntimeException('client host visitor sources'); } ++$checks;
foreach (['node' => true, 'publisher' => true, 'string' => 'true'] as $role => $value) {
    $v = $original; $v['features']['visitor_sources'] = $value;
    if ($role !== 'string') { $v['role'] = $role; $v['node']['onion'] = 'pg6mmjiyjmcrsslvykfwnntlaru7p5svn6y2ymmju6nubxndf4pscryd.onion'; $v['node']['key_path'] = $role === 'node' ? '/var/lib/onion-service-discovery/node.key' : null; }
    file_put_contents($path, json_encode($v, JSON_THROW_ON_ERROR));
    try { NodeConfig::load($path); throw new RuntimeException('visitor sources accepted for ' . $role); } catch (InvalidArgumentException) { ++$checks; }
}
// publisher_nodes: optional, publisher/combined only, distinct canonical Onions with valid Node keys.
$nodeKey = 'ed25519:' . rtrim(strtr(base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(str_repeat("\x07", 32)))), '+/', '-_'), '=');
$publisher = $original; $publisher['role'] = 'publisher'; $publisher['node']['onion'] = 'pg6mmjiyjmcrsslvykfwnntlaru7p5svn6y2ymmju6nubxndf4pscryd.onion'; $publisher['features']['publish_service'] = true;
$entry = ['onion' => '32o2tuiv2yycgn2o5qv5ff4tfsb7ytiats25pzx2i4gvrjl3tyop7mqd.onion', 'expected_node_key' => $nodeKey];
file_put_contents($path, json_encode($publisher, JSON_THROW_ON_ERROR));
if (NodeConfig::load($path)->publisherNodes !== []) { throw new RuntimeException('publisher_nodes default'); } ++$checks;
$v = $publisher; $v['publisher_nodes'] = [$entry]; file_put_contents($path, json_encode($v, JSON_THROW_ON_ERROR));
if (NodeConfig::load($path)->publisherNodes !== [$entry]) { throw new RuntimeException('publisher_nodes'); } ++$checks;
foreach (['client-role' => 'ROLE_CONFIGURATION_INVALID', 'duplicate' => 'SOURCE_DUPLICATE', 'key' => 'PUBLIC_KEY_INVALID', 'extra' => 'CONFIGURATION_INVALID', 'too-many' => 'CONFIGURATION_INVALID', 'object' => 'CONFIGURATION_INVALID'] as $case => $expected) {
    $v = $publisher;
    match ($case) {
        'client-role' => [$v = $original, $v['publisher_nodes'] = [$entry]],
        'duplicate' => $v['publisher_nodes'] = [$entry, $entry],
        'key' => $v['publisher_nodes'] = [['onion' => $entry['onion'], 'expected_node_key' => 'ed25519:invalid']],
        'extra' => $v['publisher_nodes'] = [$entry + ['label' => 'mine']],
        'too-many' => $v['publisher_nodes'] = array_fill(0, 17, $entry),
        'object' => $v['publisher_nodes'] = ['first' => $entry],
    };
    file_put_contents($path, json_encode($v, JSON_THROW_ON_ERROR));
    try { NodeConfig::load($path); throw new RuntimeException('publisher_nodes accepted: ' . $case); }
    catch (InvalidArgumentException $error) { if ($error->getMessage() !== $expected) { throw new RuntimeException($case . ' gave ' . $error->getMessage()); } ++$checks; }
}
file_put_contents($path, json_encode($original, JSON_THROW_ON_ERROR));
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
