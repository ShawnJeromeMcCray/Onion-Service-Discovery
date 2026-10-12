<?php
declare(strict_types=1);

require __DIR__ . '/../src/Domain/OnionAddress.php';

use OnionServiceDiscovery\Domain\OnionAddress;

$vectors = json_decode(file_get_contents(__DIR__ . '/../../protocol/test-vectors/onion-addresses.json'), true, 16, JSON_THROW_ON_ERROR);
$passed = 0;
foreach ($vectors['cases'] as $case) {
    try {
        OnionAddress::parse($case['input']);
        $accepted = true;
    } catch (InvalidArgumentException $error) {
        if ($error->getMessage() !== 'ADDRESS_INVALID') {
            throw $error;
        }
        $accepted = false;
    }
    if ($accepted !== $case['valid']) {
        fwrite(STDERR, 'FAIL: ' . $case['id'] . PHP_EOL);
        exit(1);
    }
    ++$passed;
}
echo "PASS: PHP OnionAddress {$passed} shared vectors", PHP_EOL;
