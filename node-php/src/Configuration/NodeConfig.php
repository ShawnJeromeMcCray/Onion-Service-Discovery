<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Configuration;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Validation\StrictJson;
use OnionServiceDiscovery\Storage\FilesystemStore;
use OnionServiceDiscovery\Storage\SQLiteStore;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Transport\TorTransport;

final readonly class NodeConfig
{
    /**
     * @param array<array-key, mixed> $features
     * @param array<array-key, mixed> $sources
     * @param list<string> $allowedClientOrigins
     */
    private function __construct(public string $role, public string $backend, public string $storagePath, public string $exportPath, public ?string $nodeOnion, public ?string $nodeKeyPath, public array $features, public array $sources, public array $allowedClientOrigins, private string $proxyHost, private int $proxyPort, private int $timeout) {}
    public static function load(string $path): self
    {
        if (str_contains($path, '://') || is_link($path) || !is_file($path) || filesize($path) > 16384) { throw new ProtocolException('CONFIGURATION_UNAVAILABLE'); }
        $bytes = file_get_contents($path); if ($bytes === false) { throw new ProtocolException('CONFIGURATION_UNAVAILABLE'); }
        $v = StrictJson::parse($bytes, 16384);
        if (count(get_object_vars($v)) !== 9 || array_diff(array_keys(get_object_vars($v)), ['configuration_version','role','storage','export_path','node','features','transport','sources','allowed_client_origins']) !== [] || ($v->configuration_version ?? null) !== 1 || !in_array($v->role ?? null, ['node','client-host','combined'], true)) { throw new ProtocolException('CONFIGURATION_INVALID'); }
        self::shape($v->storage, ['backend','path']); self::shape($v->node, ['onion','key_path']); self::shape($v->transport, ['proxy_host','proxy_port','timeout_ms']);
        self::shape($v->features, ['publish_service','mirror_directory','discover_services','sync_with_peers','server_search']);
        foreach (get_object_vars($v->features) as $flag) { if (!is_bool($flag)) { throw new ProtocolException('CONFIGURATION_INVALID'); } }
        if (!in_array($v->storage->backend, ['filesystem','sqlite'], true)) { throw new ProtocolException('STORAGE_BACKEND_INVALID'); }
        self::path($v->storage->path); self::path($v->export_path);
        if (!is_array($v->sources) || !array_is_list($v->sources) || count($v->sources) > 16 || count(array_unique($v->sources, SORT_REGULAR)) !== count($v->sources)) { throw new ProtocolException('CONFIGURATION_INVALID'); }
        foreach ($v->sources as $source) { if (!is_string($source)) { throw new ProtocolException('CONFIGURATION_INVALID'); } OnionAddress::parse($source); }
        if (!is_array($v->allowed_client_origins) || !array_is_list($v->allowed_client_origins) || count($v->allowed_client_origins) > 16) { throw new ProtocolException('CONFIGURATION_INVALID'); }
        foreach ($v->allowed_client_origins as $origin) {
            if (!is_string($origin) || preg_match('~\Ahttp://([a-z2-7]{56}\.onion)\z~D', $origin, $match) !== 1) { throw new ProtocolException('CLIENT_ORIGIN_INVALID'); }
            OnionAddress::parse($match[1]);
        }
        if (count(array_unique($v->allowed_client_origins)) !== count($v->allowed_client_origins)) { throw new ProtocolException('CONFIGURATION_INVALID'); }
        if ($v->role === 'client-host') {
            if ($v->node->onion !== null || $v->node->key_path !== null || array_filter(get_object_vars($v->features)) !== []) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID'); }
        } else {
            if (!is_string($v->node->onion)) { throw new ProtocolException('CONFIGURATION_INVALID'); } OnionAddress::parse($v->node->onion); self::path($v->node->key_path);
        }
        if (!is_string($v->transport->proxy_host) || !in_array($v->transport->proxy_host, ['127.0.0.1','::1'], true) || !is_int($v->transport->proxy_port) || $v->transport->proxy_port < 1 || $v->transport->proxy_port > 65535 || !is_int($v->transport->timeout_ms) || $v->transport->timeout_ms < 100 || $v->transport->timeout_ms > 60000) { throw new ProtocolException('CONFIGURATION_INVALID'); }
        // Reject placing persistent or signing material under the application public root.
        $public = realpath(dirname(__DIR__, 2) . '/public');
        foreach ([$v->storage->path, $v->export_path, $v->node->key_path] as $privatePath) {
            if ($privatePath !== null && $public !== false && ($privatePath === $public || str_starts_with($privatePath, $public . '/'))) { throw new ProtocolException('PRIVATE_PATH_IN_WEBROOT'); }
        }
        return new self($v->role, $v->storage->backend, $v->storage->path, $v->export_path, $v->node->onion, $v->node->key_path, get_object_vars($v->features), $v->sources, $v->allowed_client_origins, $v->transport->proxy_host, $v->transport->proxy_port, $v->transport->timeout_ms);
    }
    public function store(bool $create = false): StorageBackend { return $this->backend === 'filesystem' ? new FilesystemStore($this->storagePath, create: $create) : new SQLiteStore($this->storagePath, create: $create); }
    public function transport(): TorTransport { return new TorTransport($this->proxyHost, $this->proxyPort, $this->timeout); }
    /**
     * @param array<array-key, mixed> $keys
     */
    private static function shape(mixed $value, array $keys): void
    {
        if (!$value instanceof \stdClass || count(get_object_vars($value)) !== count($keys) || array_diff(array_keys(get_object_vars($value)), $keys) !== []) { throw new ProtocolException('CONFIGURATION_INVALID'); }
    }
    private static function path(mixed $value): void
    {
        if (!is_string($value) || strlen($value) > 1024 || !str_starts_with($value, '/') || str_contains($value, '://') || preg_match('/[\x00-\x1f\x7f]/', $value) === 1 || in_array('..', explode('/', $value), true) || in_array('.', explode('/', $value), true)) { throw new ProtocolException('CONFIGURATION_PATH_INVALID'); }
    }
}
