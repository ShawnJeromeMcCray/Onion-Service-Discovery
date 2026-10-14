<?php
declare(strict_types=1);
// Every key in the shipped example configurations is documented in the configuration reference.
$project = dirname(__DIR__, 2);
$reference = (string) file_get_contents($project . '/docs/operations/configuration.md');
$flatten = static function (array $value, string $prefix = '') use (&$flatten): array {
    $keys = [];
    foreach ($value as $key => $item) {
        $name = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        $keys = is_array($item) && !array_is_list($item) ? array_merge($keys, $flatten($item, $name)) : array_merge($keys, [$name]);
    }
    return $keys;
};
$keys = [];
foreach (['example.json', 'node.example.json', 'combined.example.json'] as $file) {
    $keys = array_merge($keys, $flatten(json_decode((string) file_get_contents($project . '/node-php/config/' . $file), true, 16, JSON_THROW_ON_ERROR)));
}
$missing = array_values(array_filter(array_unique($keys), static fn (string $key): bool => !str_contains($reference, '`' . $key . '`')));
if ($missing !== []) { throw new RuntimeException('Undocumented configuration keys: ' . implode(', ', $missing)); }
echo 'PASS: configuration reference documents all ' . count(array_unique($keys)) . " example keys\n";
