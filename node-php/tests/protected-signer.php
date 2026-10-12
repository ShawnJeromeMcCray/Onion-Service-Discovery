<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Signing\ProtectedFileSigner;
use OnionServiceDiscovery\Signing\SignerException;
use OnionServiceDiscovery\Storage\StorageJson;
$root = dirname(__DIR__, 2);
$verifier = new RecordVerifier($root . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($root . '/protocol/test-vectors/identity-sequences.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($fixtures->cases as $case) { if ($case->id === 'fork-same-revision') { $manifest = $case->records[0]; break; } }
$directory = sys_get_temp_dir() . '/osd-signer-' . bin2hex(random_bytes(12)); mkdir($directory, 0700);
$passphrase = 'TEST ONLY encrypted envelope passphrase';
$service = ProtectedFileSigner::create($directory . '/service.key', 'service', $passphrase, $verifier);
$manifest->service_identity_public_key = $service->publicKey();
$record = $verifier->verify($service->signRecord($manifest));
unset($service);
$restored = ProtectedFileSigner::open($directory . '/service.key', 'service', $passphrase, $verifier);
if ($verifier->verify($restored->signRecord($manifest))->canonicalWire !== $record->canonicalWire) { throw new RuntimeException('restore mismatch'); }
$checks = 2;
foreach (['wrong passphrase', 'role', 'tamper', 'kdf budget', 'permissions'] as $test) {
    $original = file_get_contents($directory . '/service.key');
    $envelope = StorageJson::decode($original, 4096);
    if ($test === 'tamper') { $envelope['created_at'] = '2000-01-01T00:00:00Z'; file_put_contents($directory . '/service.key', StorageJson::encode($envelope)); }
    if ($test === 'kdf budget') { $envelope['memory'] = 1099511627776; file_put_contents($directory . '/service.key', StorageJson::encode($envelope)); }
    if ($test === 'permissions') { chmod($directory . '/service.key', 0644); }
    clearstatcache();
    try {
        ProtectedFileSigner::open($directory . '/service.key', $test === 'role' ? 'node' : 'service', $test === 'wrong passphrase' ? 'incorrect test passphrase' : $passphrase, $verifier);
        throw new RuntimeException('unexpected acceptance: ' . $test);
    } catch (SignerException) { ++$checks; }
    file_put_contents($directory . '/service.key', $original); chmod($directory . '/service.key', 0600); clearstatcache();
}
$node = ProtectedFileSigner::create($directory . '/node.key', 'node', $passphrase, $verifier);
try { $node->signRecord($manifest); throw new RuntimeException('node signed service record'); } catch (SignerException) { ++$checks; }
try { $restored->signRecord($manifest, true); throw new RuntimeException('confirmation accepted for manifest'); } catch (SignerException) { ++$checks; }
$manifest->service_identity_public_key = $node->publicKey();
try { $restored->signRecord($manifest); throw new RuntimeException('wrong key accepted'); } catch (SignerException) { ++$checks; }
unset($restored, $node);
unlink($directory . '/service.key'); unlink($directory . '/node.key'); rmdir($directory);
echo 'PASS: protected signer ' . $checks . " checks\n";
