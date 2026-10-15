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
     * @param list<array{onion:string,expected_node_key:string}> $publisherNodes Discovery Nodes a publisher checks and announces to.
     */
    private function __construct(public string $role, public string $backend, public string $storagePath, public string $exportPath, public ?string $nodeOnion, public ?string $nodeKeyPath, public array $features, public array $sources, public array $allowedClientOrigins, private string $proxyHost, private int $proxyPort, private int $timeout, public array $publisherNodes = []) {}
    public static function load(string $path): self
    {
        if (str_contains($path, '://') || is_link($path) || !is_file($path) || filesize($path) > 16384) { throw new ProtocolException('CONFIGURATION_UNAVAILABLE'); }
        $publicRoot = realpath(dirname(__DIR__, 2) . '/public');
        $realConfig = realpath($path);
        if ($realConfig === false || ($publicRoot !== false && str_starts_with($realConfig, $publicRoot . '/'))) { throw new ProtocolException('PRIVATE_PATH_IN_WEBROOT'); }
        $bytes = file_get_contents($path, false, null, 0, 16385); if ($bytes === false || strlen($bytes) > 16384) { throw new ProtocolException('CONFIGURATION_UNAVAILABLE'); }
        $v = StrictJson::parse($bytes, 16384);
        // publisher_nodes is a later, optional addition; files without it stay valid.
        if (count(get_object_vars($v)) !== (property_exists($v, 'publisher_nodes') ? 10 : 9) || array_diff(array_keys(get_object_vars($v)), ['configuration_version','role','storage','export_path','node','features','transport','sources','allowed_client_origins','publisher_nodes']) !== [] || ($v->configuration_version ?? null) !== 1 || !in_array($v->role ?? null, ['node','client-host','combined','publisher'], true)) { throw new ProtocolException('CONFIGURATION_INVALID'); }
        self::shape($v->storage, ['backend','path']); self::shape($v->node, ['onion','key_path']); self::shape($v->transport, ['proxy_host','proxy_port','timeout_ms']);
        if (!$v->features instanceof \stdClass) { throw new ProtocolException('CONFIGURATION_INVALID'); }
        // Backward-compatible optional local receiver switch; disabled for existing configs.
        if (!property_exists($v->features, 'accept_announcements')) { $v->features->accept_announcements = false; }
        // Backward-compatible optional Client Host switch: relay visitor-chosen Nodes; disabled for existing configs.
        if (!property_exists($v->features, 'visitor_sources')) { $v->features->visitor_sources = false; }
        self::shape($v->features, ['publish_service','mirror_directory','discover_services','sync_with_peers','server_search','accept_announcements','visitor_sources']);
        foreach (get_object_vars($v->features) as $flag) { if (!is_bool($flag)) { throw new ProtocolException('CONFIGURATION_INVALID'); } }
        if ($v->features->accept_announcements && (!in_array($v->role, ['node','combined'], true) || !$v->features->discover_services)) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID'); }
        if ($v->features->server_search && !in_array($v->role, ['node','combined'], true)) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID'); }
        if ($v->features->visitor_sources && !in_array($v->role, ['client-host','combined'], true)) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID'); }
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
        $publisherNodes = self::publisherNodes($v->publisher_nodes ?? [], $v->role);
        if ($v->role === 'client-host') {
            // A Client Host has no Node features; only the visitor-source relay switch may be enabled.
            if ($v->node->onion !== null || $v->node->key_path !== null || array_keys(array_filter(get_object_vars($v->features))) !== array_keys(array_filter(['visitor_sources' => $v->features->visitor_sources]))) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID'); }
        } elseif ($v->role === 'publisher') {
            if (!is_string($v->node->onion) || $v->node->key_path !== null || $v->features->mirror_directory || $v->features->discover_services || $v->features->sync_with_peers || $v->allowed_client_origins !== []) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID'); }
            OnionAddress::parse($v->node->onion);
        } else {
            if (!is_string($v->node->onion)) { throw new ProtocolException('CONFIGURATION_INVALID'); } OnionAddress::parse($v->node->onion); self::path($v->node->key_path);
        }
        if (!is_string($v->transport->proxy_host) || !in_array($v->transport->proxy_host, ['127.0.0.1','::1'], true) || !is_int($v->transport->proxy_port) || $v->transport->proxy_port < 1 || $v->transport->proxy_port > 65535 || !is_int($v->transport->timeout_ms) || $v->transport->timeout_ms < 100 || $v->transport->timeout_ms > 60000) { throw new ProtocolException('CONFIGURATION_INVALID'); }
        // Reject placing persistent or signing material under the application public root.
        $public = realpath(dirname(__DIR__, 2) . '/public');
        foreach ([$v->storage->path, $v->export_path, $v->node->key_path] as $privatePath) {
            if ($privatePath !== null && $public !== false && (self::resolvedPath($privatePath) === $public || str_starts_with(self::resolvedPath($privatePath), $public . '/'))) { throw new ProtocolException('PRIVATE_PATH_IN_WEBROOT'); }
        }
        return new self($v->role, $v->storage->backend, $v->storage->path, $v->export_path, $v->node->onion, $v->node->key_path, get_object_vars($v->features), $v->sources, $v->allowed_client_origins, $v->transport->proxy_host, $v->transport->proxy_port, $v->transport->timeout_ms, $publisherNodes);
    }
    public function store(bool $create = false, bool $readOnly = false): StorageBackend
    {
        if ($this->role === 'publisher') { throw new ProtocolException('STORAGE_NOT_REQUIRED_FOR_PUBLISHER'); }
        return $this->backend === 'filesystem' ? new FilesystemStore($this->storagePath, create: $create, readOnly: $readOnly) : new SQLiteStore($this->storagePath, create: $create, readOnly: $readOnly);
    }
    public function transport(): TorTransport { return new TorTransport($this->proxyHost, $this->proxyPort, $this->timeout); }
    /**
     * The publisher's own choice of Discovery Nodes, each with the Node key obtained independently.
     * @return list<array{onion:string,expected_node_key:string}>
     */
    private static function publisherNodes(mixed $value, string $role): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > \OnionServiceDiscovery\Configuration\PublisherMembership::MAX_SOURCES) { throw new ProtocolException('CONFIGURATION_INVALID'); }
        if ($value !== [] && !in_array($role, ['publisher', 'combined'], true)) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID'); }
        $nodes = [];
        foreach ($value as $node) {
            self::shape($node, ['onion', 'expected_node_key']);
            if (!is_string($node->onion) || !is_string($node->expected_node_key)) { throw new ProtocolException('CONFIGURATION_INVALID'); }
            $onion = OnionAddress::parse($node->onion)->hostname; \OnionServiceDiscovery\Crypto\Encoding::publicKey($node->expected_node_key);
            if (isset($nodes[$onion])) { throw new ProtocolException('SOURCE_DUPLICATE'); }
            $nodes[$onion] = ['onion' => $onion, 'expected_node_key' => $node->expected_node_key];
        }
        return array_values($nodes);
    }
    /**
     * @param array<array-key, mixed> $keys
     */
    private static function shape(mixed $value, array $keys): void
    {
        if (!$value instanceof \stdClass || count(get_object_vars($value)) !== count($keys) || array_diff(array_keys(get_object_vars($value)), $keys) !== []) { throw new ProtocolException('CONFIGURATION_INVALID'); }
    }
    private static function resolvedPath(string $path): string
    {
        $remaining = []; $ancestor = $path;
        while (!file_exists($ancestor) && !is_link($ancestor) && $ancestor !== '/') {
            array_unshift($remaining, basename($ancestor)); $ancestor = dirname($ancestor);
        }
        $resolved = realpath($ancestor);
        if ($resolved === false) { throw new ProtocolException('CONFIGURATION_PATH_INVALID'); }
        return $remaining === [] ? $resolved : rtrim($resolved, '/') . '/' . implode('/', $remaining);
    }
    private static function path(mixed $value): void
    {
        if (!is_string($value) || strlen($value) > 1024 || !str_starts_with($value, '/') || str_contains($value, '://') || preg_match('/[\x00-\x1f\x7f]/', $value) === 1 || in_array('..', explode('/', $value), true) || in_array('.', explode('/', $value), true)) { throw new ProtocolException('CONFIGURATION_PATH_INVALID'); }
    }
}
