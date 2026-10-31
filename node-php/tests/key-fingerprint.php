<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Crypto\KeyFingerprint;
$vectors = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/protocol/test-vectors/key-fingerprint-v1.json'), true, 16, JSON_THROW_ON_ERROR);
foreach ($vectors['cases'] as $case) {
    if (KeyFingerprint::of($case['public_key']) !== $case['fingerprint']) { throw new RuntimeException('fingerprint mismatch for ' . $case['public_key']); }
}
try { KeyFingerprint::of('ed25519:short'); throw new RuntimeException('invalid key fingerprinted'); }
catch (InvalidArgumentException $error) { if ($error->getMessage() !== 'PUBLIC_KEY_INVALID') { throw $error; } }
foreach ($vectors['rejected'] as $case) {
    try { KeyFingerprint::of($case['public_key']); throw new RuntimeException('weak key fingerprinted: ' . $case['id']); }
    catch (InvalidArgumentException $error) { if ($error->getMessage() !== $case['error']) { throw $error; } }
}
echo 'PASS: ' . count($vectors['cases']) . ' shared D-025 key fingerprint vectors, ' . count($vectors['rejected']) . " rejected point encodings and invalid-key refusal\n";
