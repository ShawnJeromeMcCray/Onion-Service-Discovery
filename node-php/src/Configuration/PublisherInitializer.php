<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\ProtocolException;

/** Explicit local setup for every role. Creates neither keys, a database, nor a publication. */
final class PublisherInitializer
{
    public static function create(string $path, OnionAddress $onion, bool $publish = false): NodeConfig
    {
        return self::createForRole($path, 'publisher', $onion, $publish);
    }

    /**
     * Writes a validated configuration for any role with all private paths inside the
     * configuration's own owner-only directory. Creates no key, store or network request.
     * @param list<OnionAddress> $sources
     * @param list<array{0:OnionAddress,1:string}> $publisherNodes Discovery Nodes the publisher checks, each with its expected Node key.
     * @param array<string,string> $sourceKeys Expected Node keys of some listed sources, by onion address.
     */
    public static function createForRole(string $path, string $role, ?OnionAddress $onion, bool $publish = false, array $sources = [], bool $visitorSources = false, string $backend = 'filesystem', array $publisherNodes = [], array $sourceKeys = []): NodeConfig
    {
        $directory = dirname($path);
        $resolved = realpath($directory);
        $public = realpath(dirname(__DIR__, 2) . '/public');
        if ($resolved === false || is_link($directory) || $resolved !== $directory || !str_starts_with($path, '/') || str_contains($path, '://') || strlen($path) > 1024 || preg_match('/[\x00-\x1f\x7f]/', $path) === 1 || in_array(basename($path), ['.','..',''], true) || (fileperms($directory) & 0077) !== 0 || ($public !== false && ($resolved === $public || str_starts_with($resolved, $public . '/')))) {
            throw new ProtocolException('CONFIGURATION_PATH_UNSAFE');
        }
        $nodeRole = in_array($role, ['node', 'combined'], true);
        // Refused before anything is written: the loader would reject the finished file.
        $chosen = array_map(static fn (array $node): string => $node[0]->hostname, $publisherNodes);
        if (count(array_unique($chosen)) !== count($chosen)) { throw new ProtocolException('SOURCE_DUPLICATE'); }
        if (!in_array($role, ['publisher', 'node', 'combined', 'client-host'], true) || ($role === 'client-host') !== ($onion === null) || !in_array($backend, ['filesystem', 'sqlite'], true)
            || ($sources !== [] && !in_array($role, ['client-host', 'combined'], true)) || ($visitorSources && !in_array($role, ['client-host', 'combined'], true)) || ($publish && $role === 'client-host') || count($sources) > 16
            || ($publisherNodes !== [] && !in_array($role, ['publisher', 'combined'], true)) || count($publisherNodes) > PublisherMembership::MAX_SOURCES) {
            throw new ProtocolException('USAGE_ERROR');
        }
        $data = ['configuration_version' => 1, 'role' => $role,
            'storage' => ['backend' => $backend, 'path' => $directory . ($role === 'publisher' ? '/publisher-state' : ($backend === 'sqlite' ? '/data.sqlite' : '/data'))],
            'export_path' => $directory . ($role === 'publisher' ? '/public-evidence-cache' : '/exports'),
            'node' => ['onion' => $onion?->hostname, 'key_path' => $nodeRole ? $directory . '/node.key' : null],
            'features' => ['publish_service' => $publish, 'mirror_directory' => $nodeRole, 'discover_services' => $nodeRole, 'sync_with_peers' => false, 'server_search' => false, 'accept_announcements' => false, 'visitor_sources' => $visitorSources],
            'transport' => ['proxy_host' => '127.0.0.1', 'proxy_port' => 9050, 'timeout_ms' => 30000],
            'sources' => array_values(array_unique(array_map(static fn (OnionAddress $source): string => $source->hostname, $sources))), 'allowed_client_origins' => []];
        // Roles that can publish get the list even when empty, so the file shows where Nodes go.
        if (in_array($role, ['publisher', 'combined'], true)) {
            $data['publisher_nodes'] = array_map(static fn (array $node): array => ['onion' => $node[0]->hostname, 'expected_node_key' => $node[1]], $publisherNodes);
        }
        if ($sourceKeys !== []) { $data['source_node_keys'] = $sourceKeys; }
        $bytes = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $oldMask = umask(0077);
        try { $file = @fopen($path, 'xb'); } finally { umask($oldMask); }
        if ($file === false) { throw new ProtocolException('CONFIGURATION_CREATE_REFUSED'); }
        try {
            if (fwrite($file, $bytes) !== strlen($bytes) || !fflush($file) || !fsync($file)) { throw new ProtocolException('CONFIGURATION_WRITE_FAILED'); }
        } finally { fclose($file); }
        $parent = fopen($directory, 'r');
        if ($parent === false) { throw new ProtocolException('CONFIGURATION_WRITE_FAILED'); }
        try { if (!fsync($parent)) { throw new ProtocolException('CONFIGURATION_WRITE_FAILED'); } } finally { fclose($parent); }
        return NodeConfig::load($path);
    }
}
