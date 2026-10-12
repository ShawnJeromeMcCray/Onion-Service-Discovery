<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Node\NodeInitializer;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
$project = dirname(__DIR__, 2); $verifier = new RecordVerifier($project . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($project . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $onion = $case->records[0]->onion; break; } }
$root = sys_get_temp_dir() . '/osd-node-init-' . bin2hex(random_bytes(12)); mkdir($root, 0700);
$signer = ProtectedFileSigner::create($root . '/node.key', 'node', 'TEST ONLY node initialization passphrase', $verifier);
$v = json_decode(file_get_contents($project . '/node-php/config/example.json'), true, 32, JSON_THROW_ON_ERROR);
$v['role'] = 'node'; $v['node'] = ['onion' => $onion, 'key_path' => $root . '/node.key']; $v['storage']['path'] = $root . '/data'; $v['export_path'] = $root . '/exports'; $v['features']['mirror_directory'] = true;
file_put_contents($root . '/config.json', json_encode($v, JSON_THROW_ON_ERROR)); $config = NodeConfig::load($root . '/config.json');
$first = NodeInitializer::initialize($config, $signer, $verifier); $second = NodeInitializer::initialize($config, $signer, $verifier);
if ($first['status'] !== 'INITIALIZED' || $second['status'] !== 'UNCHANGED' || $first['descriptor_hash'] !== $second['descriptor_hash']) { throw new RuntimeException('initialization not idempotent'); }
$store = $config->store(); $descriptor = $verifier->verifyWire($store->readRecord($first['descriptor_hash']));
if ($descriptor->type !== 'node_descriptor' || $descriptor->document()->node_onion !== $onion) { throw new RuntimeException('descriptor binding'); }
$v['features']['sync_with_peers'] = true; file_put_contents($root . '/config.json', json_encode($v, JSON_THROW_ON_ERROR));
$next = NodeInitializer::initialize(NodeConfig::load($root . '/config.json'), $signer, $verifier);
$d = $verifier->verifyWire($store->readRecord($next['descriptor_hash']))->document();
if ($d->descriptor_revision !== 2 || $d->previous_descriptor_hash !== $first['descriptor_hash']) { throw new RuntimeException('descriptor chain'); }
unset($store,$config,$signer);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($root);
echo "PASS: Node initialization, explicit features and descriptor chain\n";
