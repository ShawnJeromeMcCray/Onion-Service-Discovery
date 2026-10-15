<?php
declare(strict_types=1);
// Spec 18 section 7: request likely secret paths through the production front controller
// and assert that none is served. Every role runs with real private files next to its
// configuration; their paths and contents carry a random marker that must never appear
// in a response. No Tor is reachable here.
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Storage\PublicBlobStore;

$project = dirname(__DIR__, 2);
$marker = 'osd-secret-marker-' . bin2hex(random_bytes(8));
$root = sys_get_temp_dir() . '/osd-secret-paths-' . $marker; mkdir($root, 0700);
$fixture = json_decode((string) file_get_contents($project . '/protocol/test-vectors/browser-dataset.json'), true, 64, JSON_THROW_ON_ERROR);
$onion = $fixture['sources'][0]['onion']; $source = $fixture['sources'][1]['onion'];
$paths = ['/config.json', '/node.json', '/publisher.json', '/.env', '/.git/config', '/.git/HEAD', '/composer.json', '/composer.lock',
    '/vendor/autoload.php', '/index.php.bak', '/.well-known/.publisher.lock', '/.well-known/onion-service-discovery.json~', '/data/', '/exports/',
    '/backup.ndjson', '/node.key', '/passphrase', '/onion-service-discovery/../../etc/passwd', '/onion-service-discovery/%2e%2e/config.json',
    '/node-php/config/node.example.json', '/web-local-search-client/package.json', '/protocol/schemas/service_manifest.json', '/hs_ed25519_secret_key',
    // The front controller itself, the private files under their real names and further traversal forms.
    '/index.php', '/node-php/public/index.php', '/node-passphrase', '/node-php/composer.json', '/node-php/vendor/autoload.php', '/.gitignore',
    '/onion-service-discovery/../config.json', '/onion-service-discovery/v1/../../config.json', '/onion-service-discovery/status/../../node.key',
    '/onion-service-discovery/relay/..%2f..%2fconfig.json', '/onion-service-discovery/search/..%2f..%2fconfig.json', '/.well-known/../config.json',
    '/.well-known/onion-service-discovery-history.json.bak', '/config.json%00.html', $root . '/node/config.json', '/' . basename($root) . '/node/node.key'];
// Strings from the private files, repository files and system files that a response must never contain.
$needles = [$marker, '<?php', 'declare(strict_types', 'opis/json-schema', 'ComposerAutoloaderInit', 'repositoryformatversion', 'gitdir:',
    'node_modules/', 'devDependencies', 'configuration_version', '"$schema"', 'root:x:0:', $root];
$base = json_decode((string) file_get_contents($project . '/node-php/config/combined.example.json'), true, 32, JSON_THROW_ON_ERROR);
$roles = [
    'publisher' => ['role' => 'publisher', 'node' => ['onion' => $onion, 'key_path' => null], 'features' => ['publish_service' => true]],
    'node' => ['role' => 'node', 'features' => ['mirror_directory' => true, 'discover_services' => true, 'accept_announcements' => true]],
    'combined' => ['role' => 'combined', 'sources' => [$source], 'features' => ['mirror_directory' => true, 'discover_services' => true, 'accept_announcements' => true, 'server_search' => true, 'visitor_sources' => true]],
    'client-host' => ['role' => 'client-host', 'node' => ['onion' => null, 'key_path' => null], 'sources' => [$source], 'features' => ['visitor_sources' => true]],
];
// A request that proves the front controller loaded this role's configuration and routes.
$controls = [
    'publisher' => ['/onion-service-discovery/status/', 200, '<h1>Onion Service Discovery</h1>'],
    'node' => ['/onion-service-discovery/v1/peers', 200, '"peers":[]'],
    'combined' => ['/onion-service-discovery/config.json', 200, '"mode":"combined"'],
    'client-host' => ['/onion-service-discovery/config.json', 200, '"mode":"client-host"'],
];
$requests = 0;
try {
    foreach ($roles as $name => $settings) {
        $dir = $root . '/' . $name; mkdir($dir, 0700);
        $config = array_replace($base, ['storage' => ['backend' => 'filesystem', 'path' => $dir . '/data'], 'export_path' => $dir . '/exports', 'node' => ['onion' => $onion, 'key_path' => $dir . '/node.key']], array_diff_key($settings, ['features' => true]));
        $config['features'] = array_replace($base['features'], $settings['features']);
        file_put_contents($dir . '/config.json', json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)); chmod($dir . '/config.json', 0600);
        foreach (['node.key', 'node-passphrase', 'backup.ndjson', '.env', 'publisher.json'] as $file) { file_put_contents($dir . '/' . $file, 'TEST ONLY ' . $marker . "\n"); chmod($dir . '/' . $file, 0600); }
        $node = NodeConfig::load($dir . '/config.json');
        if ($node->role !== 'publisher') { $node->store(true); new PublicBlobStore($node->exportPath, true); unset($node); }
        $listener = stream_socket_server('tcp://127.0.0.1:0'); $port = (int) substr((string) strrchr((string) stream_socket_get_name($listener, false), ':'), 1); fclose($listener);
        $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $project . '/node-php/public', $project . '/node-php/public/index.php'], [['pipe', 'r'], ['file', $dir . '/server.log', 'a'], ['file', $dir . '/server.log', 'a']], $pipes, null, ['ONION_SERVICE_DISCOVERY_CONFIG' => $dir . '/config.json']);
        fclose($pipes[0]);
        try {
            $deadline = microtime(true) + 5;
            do { $probe = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, .1); if ($probe !== false) { fclose($probe); break; } usleep(100000); } while (microtime(true) < $deadline);
            if ($probe === false) { throw new RuntimeException('front controller startup failed for ' . $name); }
            $request = static function (string $method, string $path) use ($port, $dir): array {
                // The shared request budgets are covered by request-budget.php; reset them so every request reaches its route.
                foreach (['.request-budget', '.relay-budget', '.visitor-relay-budget'] as $budget) { if (is_file($dir . '/exports/' . $budget)) { unlink($dir . '/exports/' . $budget); } }
                $socket = stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 2); stream_set_timeout($socket, 5);
                fwrite($socket, $method . ' ' . $path . " HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n");
                $reply = (string) stream_get_contents($socket, 1048576); fclose($socket);
                if (preg_match('~\AHTTP/1\.[01] ([0-9]{3}) ~', $reply, $match) !== 1) { throw new RuntimeException('invalid HTTP response for ' . $path); }
                return [(int) $match[1], $reply];
            };
            [$control, $controlStatus, $controlText] = $controls[$name];
            [$status, $reply] = $request('GET', $control);
            if ($status !== $controlStatus || !str_contains($reply, $controlText)) { throw new RuntimeException('front controller not configured for ' . $name . ': ' . $status); }
            foreach ($paths as $path) {
                foreach (['GET', 'HEAD'] as $method) {
                    [$status, $reply] = $request($method, $path); ++$requests;
                    // A refusal, never a served file or a server failure that could hide a misconfiguration.
                    if (!in_array($status, [400, 403, 404, 405], true)) { throw new RuntimeException($name . ' ' . $method . ' ' . $path . ' answered ' . $status); }
                    foreach ($needles as $needle) { if (str_contains($reply, $needle)) { throw new RuntimeException($name . ' ' . $method . ' ' . $path . ' disclosed private or repository content'); } }
                }
            }
        } finally { proc_terminate($process); proc_close($process); }
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo 'PASS: ' . $requests . " likely secret-path requests refused without private or repository content for the publisher, node, combined and client-host roles (spec 18 section 7)\n";
