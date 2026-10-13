<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;

use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\ProtocolException;

/** Explicit local setup. Creates neither keys, a database, nor a publication. */
final class PublisherInitializer
{
    public static function create(string $path, OnionAddress $onion, bool $publish = false): NodeConfig
    {
        $directory = dirname($path);
        $resolved = realpath($directory);
        $public = realpath(dirname(__DIR__, 2) . '/public');
        if ($resolved === false || is_link($directory) || $resolved !== $directory || !str_starts_with($path, '/') || str_contains($path, '://') || strlen($path) > 1024 || preg_match('/[\x00-\x1f\x7f]/', $path) === 1 || in_array(basename($path), ['.','..',''], true) || (fileperms($directory) & 0077) !== 0 || ($public !== false && ($resolved === $public || str_starts_with($resolved, $public . '/')))) {
            throw new ProtocolException('CONFIGURATION_PATH_UNSAFE');
        }
        $data = ['configuration_version' => 1, 'role' => 'publisher', 'storage' => ['backend' => 'filesystem', 'path' => $directory . '/publisher-state'], 'export_path' => $directory . '/public-evidence-cache', 'node' => ['onion' => $onion->hostname, 'key_path' => null], 'features' => ['publish_service' => $publish, 'mirror_directory' => false, 'discover_services' => false, 'sync_with_peers' => false, 'server_search' => false], 'transport' => ['proxy_host' => '127.0.0.1', 'proxy_port' => 9050, 'timeout_ms' => 30000], 'sources' => [], 'allowed_client_origins' => []];
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
