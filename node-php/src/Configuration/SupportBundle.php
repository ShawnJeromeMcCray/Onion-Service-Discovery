<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Protocol\RecordVerifier;

/**
 * Privacy-reviewed support bundle (spec 32 sections 41-42): versions, health and a redacted
 * configuration summary. Never paths, addresses, keys, passphrases, pins, queries or peer lists.
 */
final class SupportBundle
{
    /** @return array<string,mixed> */
    public static function create(NodeConfig $config, RecordVerifier $verifier, string $configPath, ?string $manifestPath = null): array
    {
        $diagnostics = Diagnostics::inspect($config, $verifier, $manifestPath, $configPath);
        $extensions = [];
        foreach (['json', 'sodium', 'iconv', 'curl', 'pdo_sqlite', 'mbstring', 'intl'] as $extension) { $extensions[$extension] = extension_loaded($extension); }
        $bundle = [
            'status' => 'PASS',
            'bundle_version' => 1,
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'software_version' => '0.1.0-dev',
            'protocol_versions' => [1],
            'runtime' => ['php_version' => PHP_VERSION, 'php_sapi' => PHP_SAPI, 'os_family' => PHP_OS_FAMILY, 'int_size' => PHP_INT_SIZE, 'extensions' => $extensions],
            'configuration' => [
                'role' => $config->role, 'storage_backend' => $config->backend, 'features' => $config->features,
                'node_onion_configured' => $config->nodeOnion !== null, 'node_key_configured' => $config->nodeKeyPath !== null,
                'source_count' => count($config->sources), 'publisher_node_count' => count($config->publisherNodes), 'allowed_client_origin_count' => count($config->allowedClientOrigins),
            ],
            'diagnostics' => $diagnostics,
            'excluded' => ['filesystem_paths', 'onion_addresses', 'public_and_private_keys', 'passphrases', 'peer_and_source_lists', 'identity_pins', 'search_queries', 'raw_logs'],
            'network_side_effects' => false,
        ];
        // Defence in depth: any configured private value that reached a nested field is replaced.
        $secrets = array_values(array_filter(array_merge([$configPath, dirname($configPath), $config->storagePath, $config->exportPath, $config->nodeOnion, $config->nodeKeyPath],
            array_map(static fn (mixed $value): ?string => is_string($value) ? $value : null, $config->sources), $config->allowedClientOrigins),
            static fn (?string $value): bool => is_string($value) && strlen($value) >= 4));
        $redacted = self::redact($bundle, $secrets);
        return is_array($redacted) ? $redacted : [];
    }

    /** @param list<string> $secrets */
    private static function redact(mixed $value, array $secrets): mixed
    {
        if (is_array($value)) { return array_map(static fn (mixed $item): mixed => self::redact($item, $secrets), $value); }
        if (!is_string($value)) { return $value; }
        foreach ($secrets as $secret) { if (str_contains($value, $secret)) { return '[REDACTED]'; } }
        return preg_match('/[a-z2-7]{56}\.onion|ed25519:[A-Za-z0-9_-]{43}/', $value) === 1 ? '[REDACTED]' : $value;
    }
}
