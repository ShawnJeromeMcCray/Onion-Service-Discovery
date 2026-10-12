<?php
declare(strict_types=1);

require __DIR__ . '/../src/Validation/StrictJson.php';

use OnionServiceDiscovery\Validation\StrictJson;

$fixtureBytes = file_get_contents(__DIR__ . '/../../protocol/test-vectors/strict-json.json');
$vectors = json_decode($fixtureBytes, true, 32, JSON_THROW_ON_ERROR);
$goldenVectors = json_decode($fixtureBytes, false, 32, JSON_THROW_ON_ERROR);
$passed = 0;
foreach ($vectors['cases'] as $index => $case) {
    $errorCode = null;
    try {
        $input = base64_decode($case['input_base64'], true);
        if ($input === false) {
            throw new RuntimeException('Malformed fixture');
        }
        $result = StrictJson::parse($input, $case['max_bytes'] ?? 16384);
        if (!isset($case['error'])) {
            $expected = $goldenVectors->cases[$index]->expected;
            if (json_encode($result, JSON_THROW_ON_ERROR) !== json_encode($expected, JSON_THROW_ON_ERROR)) {
                throw new RuntimeException('Parsed value mismatch: ' . $case['id']);
            }
        }
    } catch (InvalidArgumentException $error) {
        $errorCode = $error->getMessage();
    }
    if ($errorCode !== ($case['error'] ?? null)) {
        fwrite(STDERR, 'FAIL: ' . $case['id'] . ' expected ' . ($case['error'] ?? 'ACCEPT') . ' got ' . ($errorCode ?? 'ACCEPT') . PHP_EOL);
        exit(1);
    }
    ++$passed;
}
echo "PASS: PHP StrictJson {$passed} shared vectors", PHP_EOL;
