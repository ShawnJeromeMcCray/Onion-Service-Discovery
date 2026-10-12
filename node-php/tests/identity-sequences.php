<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\RecordVerifier;

$root = dirname(__DIR__, 2);
$verifier = new RecordVerifier($root . '/protocol/schemas');
$fixtureBytes = file_get_contents($root . '/protocol/test-vectors/identity-sequences.json');
$vectors = json_decode($fixtureBytes, false, 64, JSON_THROW_ON_ERROR);
$passed = 0;
foreach ($vectors->cases as $case) {
    $records = array_map(static fn (stdClass $record) => $verifier->verify($record), $case->records);
    $error = null;
    try {
        $result = IdentityDeriver::derive($records, $case->max_records ?? 100000);
        $resultObject = json_decode(json_encode($result, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
        if (!isset($case->error) && CanonicalJson::encode($resultObject) !== CanonicalJson::encode($case->expected)) {
            throw new RuntimeException('Derived state mismatch: ' . $case->id);
        }
        if (!isset($case->error)) {
            $reversed = IdentityDeriver::derive(array_reverse(array_merge($records, $records)));
            $reversedObject = json_decode(json_encode($reversed, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
            if (CanonicalJson::encode($reversedObject) !== CanonicalJson::encode($resultObject)) {
                throw new RuntimeException('Order/idempotency mismatch: ' . $case->id);
            }
        }
    } catch (InvalidArgumentException $caught) {
        $error = $caught->getMessage();
    }
    if ($error !== ($case->error ?? null)) {
        fwrite(STDERR, 'FAIL identity: ' . $case->id . ' got ' . ($error ?? 'ACCEPT') . PHP_EOL);
        exit(1);
    }
    ++$passed;
}
echo "PASS: PHP identity {$passed} shared sequences and duplicate/reversal properties", PHP_EOL;
