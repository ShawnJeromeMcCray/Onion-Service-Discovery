<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordFormat;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Validation\StrictJson;

$root = dirname(__DIR__, 2);
$verifier = new RecordVerifier($root . '/protocol/schemas');
$fixtures = json_decode(file_get_contents($root . '/protocol/test-vectors/signed-records.json'), true, 32, JSON_THROW_ON_ERROR);
$passed = 0;
foreach ($fixtures['cases'] as $case) {
    $error = null;
    try {
        $binding = isset($case['expected_onion']) ? OnionAddress::parse($case['expected_onion']) : null;
        $validated = $verifier->verifyWire(base64_decode($case['input_base64'], true), $binding);
        if (!isset($case['error'])) {
            $document = $validated->document();
            if ($validated->contentHash !== $case['expected_hash'] || $validated->canonicalWire !== $case['expected_wire'] ||
                bin2hex(RecordFormat::message($document)) !== $case['expected_message_hex'] ||
                bin2hex(CanonicalJson::encode(RecordFormat::payload($document))) !== $case['expected_payload_hex']) {
                throw new RuntimeException('Exact bytes differ: ' . $case['id']);
            }
            $copy = $validated->document();
            $copy->protocol = 'mutated';
            if ($validated->document()->protocol !== 'Onion Service Discovery') {
                throw new RuntimeException('Verified record is mutable');
            }
        }
    } catch (InvalidArgumentException $caught) {
        $error = $caught->getMessage();
    }
    if ($error !== ($case['error'] ?? null)) {
        fwrite(STDERR, 'FAIL: ' . $case['id'] . ' expected ' . ($case['error'] ?? 'ACCEPT') . ' got ' . ($error ?? 'ACCEPT') . PHP_EOL);
        exit(1);
    }
    ++$passed;
}
$canonical = json_decode(file_get_contents($root . '/protocol/test-vectors/canonical-json.json'), true, 32, JSON_THROW_ON_ERROR);
foreach ($canonical['cases'] as $case) {
    if (bin2hex(CanonicalJson::encode(StrictJson::parse($case['input']))) !== $case['expected_hex']) {
        fwrite(STDERR, 'FAIL canonical bytes: ' . $case['id'] . PHP_EOL);
        exit(1);
    }
    ++$passed;
}
echo "PASS: PHP protocol core {$passed} shared cases", PHP_EOL;
