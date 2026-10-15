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
     * @param array<string,string> $sourceNodeKeys Expected Node keys of listed sources, handed to the browser client.
     */
    private function __construct(public string $role, public string $backend, public string $storagePath, public string $exportPath, public ?string $nodeOnion, public ?string $nodeKeyPath, public array $features, public array $sources, public array $allowedClientOrigins, private string $proxyHost, private int $proxyPort, private int $timeout, public array $publisherNodes = [], public array $sourceNodeKeys = []) {}
    public static function load(string $path): self
    {
        if (str_contains($path, '://') || is_link($path) || !is_file($path) || filesize($path) > 16384) { throw new ProtocolException('CONFIGURATION_UNAVAILABLE'); }
        $publicRoot = realpath(dirname(__DIR__, 2) . '/public');
        $realConfig = realpath($path);
        if ($realConfig === false || ($publicRoot !== false && str_starts_with($realConfig, $publicRoot . '/'))) { throw new ProtocolException('PRIVATE_PATH_IN_WEBROOT'); }
        $bytes = file_get_contents($path, false, null, 0, 16385); if ($bytes === false || strlen($bytes) > 16384) { throw new ProtocolException('CONFIGURATION_UNAVAILABLE'); }
        $v = StrictJson::parse($bytes, 16384);
        // Every refusal names the key path and the rule (spec 32 section 12), never the value.
        // publisher_nodes is a later, optional addition; files without it stay valid.
        $known = ['configuration_version','role','storage','export_path','node','features','transport','sources','allowed_client_origins','publisher_nodes','source_node_keys'];
        foreach (array_keys(get_object_vars($v)) as $key) { if (!in_array($key, $known, true)) { throw self::invalid((string) $key, 'unknown key'); } }
        foreach (array_slice($known, 0, 9) as $key) { if (!property_exists($v, $key)) { throw self::invalid($key, 'missing'); } }
        if ($v->configuration_version !== 1) { throw self::invalid('configuration_version', 'must be 1'); }
        if (!in_array($v->role, ['node','client-host','combined','publisher'], true)) { throw self::invalid('role', 'must be publisher, node, combined or client-host'); }
        self::shape($v->storage, ['backend','path'], 'storage'); self::shape($v->node, ['onion','key_path'], 'node'); self::shape($v->transport, ['proxy_host','proxy_port','timeout_ms'], 'transport');
        if (!$v->features instanceof \stdClass) { throw self::invalid('features', 'must be an object'); }
        // Backward-compatible optional local receiver switch; disabled for existing configs.
        if (!property_exists($v->features, 'accept_announcements')) { $v->features->accept_announcements = false; }
        // Backward-compatible optional Client Host switch: relay visitor-chosen Nodes; disabled for existing configs.
        if (!property_exists($v->features, 'visitor_sources')) { $v->features->visitor_sources = false; }
        self::shape($v->features, ['publish_service','mirror_directory','discover_services','sync_with_peers','server_search','accept_announcements','visitor_sources'], 'features');
        foreach (get_object_vars($v->features) as $name => $flag) { if (!is_bool($flag)) { throw self::invalid('features.' . $name, 'must be true or false'); } }
        if ($v->features->accept_announcements && (!in_array($v->role, ['node','combined'], true) || !$v->features->discover_services)) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID', 'features.accept_announcements: needs features.discover_services and the node or combined role'); }
        if ($v->features->server_search && !in_array($v->role, ['node','combined'], true)) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID', 'features.server_search: needs the node or combined role'); }
        if ($v->features->visitor_sources && !in_array($v->role, ['client-host','combined'], true)) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID', 'features.visitor_sources: needs the client-host or combined role'); }
        if (!in_array($v->storage->backend, ['filesystem','sqlite'], true)) { throw new ProtocolException('STORAGE_BACKEND_INVALID', 'storage.backend: must be filesystem or sqlite'); }
        self::path($v->storage->path, 'storage.path'); self::path($v->export_path, 'export_path');
        if (!is_array($v->sources) || !array_is_list($v->sources)) { throw self::invalid('sources', 'must be a list'); }
        if (count($v->sources) > 16) { throw self::invalid('sources', 'at most 16 entries'); }
        if (count(array_unique($v->sources, SORT_REGULAR)) !== count($v->sources)) { throw self::invalid('sources', 'entries must be distinct'); }
        foreach ($v->sources as $index => $source) { if (!is_string($source)) { throw self::invalid('sources[' . $index . ']', 'must be a string'); } self::onion($source, 'sources[' . $index . ']'); }
        if (!is_array($v->allowed_client_origins) || !array_is_list($v->allowed_client_origins)) { throw self::invalid('allowed_client_origins', 'must be a list'); }
        if (count($v->allowed_client_origins) > 16) { throw self::invalid('allowed_client_origins', 'at most 16 entries'); }
        foreach ($v->allowed_client_origins as $index => $origin) {
            if (!is_string($origin) || preg_match('~\Ahttp://([a-z2-7]{56}\.onion)\z~D', $origin, $match) !== 1) { throw new ProtocolException('CLIENT_ORIGIN_INVALID', 'allowed_client_origins[' . $index . ']: must be http:// followed by a v3 onion address'); }
            self::onion($match[1], 'allowed_client_origins[' . $index . ']');
        }
        if (count(array_unique($v->allowed_client_origins)) !== count($v->allowed_client_origins)) { throw self::invalid('allowed_client_origins', 'entries must be distinct'); }
        $publisherNodes = self::publisherNodes($v->publisher_nodes ?? [], $v->role);
        $sourceNodeKeys = self::sourceNodeKeys($v->source_node_keys ?? new \stdClass(), $v->sources, $v->role);
        if ($v->role === 'client-host') {
            // A Client Host has no Node features; only the visitor-source relay switch may be enabled.
            if ($v->node->onion !== null || $v->node->key_path !== null || array_keys(array_filter(get_object_vars($v->features))) !== array_keys(array_filter(['visitor_sources' => $v->features->visitor_sources]))) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID', 'role client-host: node.onion and node.key_path must be null and only features.visitor_sources may be true'); }
        } elseif ($v->role === 'publisher') {
            if (!is_string($v->node->onion) || $v->node->key_path !== null || $v->features->mirror_directory || $v->features->discover_services || $v->features->sync_with_peers || $v->allowed_client_origins !== []) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID', 'role publisher: needs node.onion, node.key_path null, no Node features and an empty allowed_client_origins'); }
            self::onion($v->node->onion, 'node.onion');
        } else {
            if (!is_string($v->node->onion)) { throw self::invalid('node.onion', 'required for the ' . $v->role . ' role'); }
            self::onion($v->node->onion, 'node.onion'); self::path($v->node->key_path, 'node.key_path');
        }
        if (!is_string($v->transport->proxy_host) || !in_array($v->transport->proxy_host, ['127.0.0.1','::1'], true)) { throw self::invalid('transport.proxy_host', 'must be 127.0.0.1 or ::1'); }
        if (!is_int($v->transport->proxy_port) || $v->transport->proxy_port < 1 || $v->transport->proxy_port > 65535) { throw self::invalid('transport.proxy_port', 'must be an integer from 1 to 65535'); }
        if (!is_int($v->transport->timeout_ms) || $v->transport->timeout_ms < 100 || $v->transport->timeout_ms > 60000) { throw self::invalid('transport.timeout_ms', 'must be an integer from 100 to 60000'); }
        // Reject placing persistent or signing material under the application public root.
        $public = realpath(dirname(__DIR__, 2) . '/public');
        foreach (['storage.path' => $v->storage->path, 'export_path' => $v->export_path, 'node.key_path' => $v->node->key_path] as $key => $privatePath) {
            if ($privatePath !== null && $public !== false && (self::resolvedPath($privatePath) === $public || str_starts_with(self::resolvedPath($privatePath), $public . '/'))) { throw new ProtocolException('PRIVATE_PATH_IN_WEBROOT', $key . ': inside node-php/public'); }
        }
        return new self($v->role, $v->storage->backend, $v->storage->path, $v->export_path, $v->node->onion, $v->node->key_path, get_object_vars($v->features), $v->sources, $v->allowed_client_origins, $v->transport->proxy_host, $v->transport->proxy_port, $v->transport->timeout_ms, $publisherNodes, $sourceNodeKeys);
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
        if (!is_array($value) || !array_is_list($value)) { throw self::invalid('publisher_nodes', 'must be a list'); }
        if (count($value) > \OnionServiceDiscovery\Configuration\PublisherMembership::MAX_SOURCES) { throw self::invalid('publisher_nodes', 'at most 16 entries'); }
        if ($value !== [] && !in_array($role, ['publisher', 'combined'], true)) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID', 'publisher_nodes: only for the publisher and combined roles'); }
        $nodes = [];
        foreach ($value as $index => $node) {
            $key = 'publisher_nodes[' . $index . ']';
            self::shape($node, ['onion', 'expected_node_key'], $key);
            if (!is_string($node->onion)) { throw self::invalid($key . '.onion', 'must be a string'); }
            if (!is_string($node->expected_node_key)) { throw self::invalid($key . '.expected_node_key', 'must be a string'); }
            self::onion($node->onion, $key . '.onion'); $onion = OnionAddress::parse($node->onion)->hostname;
            try { \OnionServiceDiscovery\Crypto\Encoding::publicKey($node->expected_node_key); }
            catch (\InvalidArgumentException $error) { throw new ProtocolException($error->getMessage(), $key . '.expected_node_key: must be ed25519: followed by 43 base64url characters'); }
            if (isset($nodes[$onion])) { throw new ProtocolException('SOURCE_DUPLICATE', $key . '.onion: listed twice'); }
            $nodes[$onion] = ['onion' => $onion, 'expected_node_key' => $node->expected_node_key];
        }
        return array_values($nodes);
    }
    /**
     * Optional expected Node keys for listed sources: the browser client then refuses a source that
     * signs with another key from the first load on, instead of trusting its first contact.
     * @param list<string> $sources
     * @return array<string,string>
     */
    private static function sourceNodeKeys(mixed $value, array $sources, string $role): array
    {
        if (!$value instanceof \stdClass) { throw self::invalid('source_node_keys', 'must be an object of onion address to expected Node key'); }
        $keys = get_object_vars($value);
        if ($keys !== [] && !in_array($role, ['client-host', 'combined'], true)) { throw new ProtocolException('ROLE_CONFIGURATION_INVALID', 'source_node_keys: only for the client-host and combined roles'); }
        foreach ($keys as $onion => $key) {
            if (!in_array($onion, $sources, true)) { throw self::invalid('source_node_keys.' . $onion, 'not listed in sources'); }
            if (!is_string($key)) { throw self::invalid('source_node_keys.' . $onion, 'must be a string'); }
            try { \OnionServiceDiscovery\Crypto\Encoding::publicKey($key); }
            catch (\InvalidArgumentException $error) { throw new ProtocolException($error->getMessage(), 'source_node_keys.' . $onion . ': must be ed25519: followed by 43 base64url characters'); }
        }
        /** @var array<string,string> $keys */
        return $keys;
    }
    private static function invalid(string $key, string $reason): ProtocolException { return new ProtocolException('CONFIGURATION_INVALID', $key . ': ' . $reason); }
    private static function onion(string $value, string $key): void
    {
        try { OnionAddress::parse($value); }
        catch (\InvalidArgumentException $error) { throw new ProtocolException($error->getMessage(), $key . ': must be a canonical v3 onion address (56 lowercase characters and .onion)'); }
    }
    /**
     * @param list<string> $keys
     */
    private static function shape(mixed $value, array $keys, string $name): void
    {
        if (!$value instanceof \stdClass) { throw self::invalid($name, 'must be an object'); }
        foreach (array_keys(get_object_vars($value)) as $key) { if (!in_array($key, $keys, true)) { throw self::invalid($name . '.' . $key, 'unknown key'); } }
        foreach ($keys as $key) { if (!property_exists($value, $key)) { throw self::invalid($name . '.' . $key, 'missing'); } }
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
    private static function path(mixed $value, string $key): void
    {
        if (!is_string($value) || strlen($value) > 1024 || !str_starts_with($value, '/') || str_contains($value, '://') || preg_match('/[\x00-\x1f\x7f]/', $value) === 1 || in_array('..', explode('/', $value), true) || in_array('.', explode('/', $value), true)) {
            throw new ProtocolException('CONFIGURATION_PATH_INVALID', $key . ': must be an absolute path of at most 1024 bytes without . or .. segments');
        }
    }
}
