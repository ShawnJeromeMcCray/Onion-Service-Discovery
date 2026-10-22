<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use OnionServiceDiscovery\Configuration\Diagnostics;
// D-028: doctor compares the served browser client with its build manifest.
$check = static fn (string $dist): string => (new ReflectionMethod(Diagnostics::class, 'clientAssets'))->invoke(null, $dist);
$root = sys_get_temp_dir() . '/osd-client-assets-' . bin2hex(random_bytes(8)); mkdir($root, 0700);
try {
    $fail = static function (string $message): never { throw new RuntimeException($message); };
    if ($check($root) !== 'WARN') { $fail('an unbuilt client is not a warning'); }
    $files = ['client.js' => "console.log('ok');\n", 'index.html' => "<!doctype html>\n"];
    $entries = [];
    foreach ($files as $name => $body) { file_put_contents($root . '/' . $name, $body); $entries[] = ['file' => $name, 'bytes' => strlen($body), 'sha256' => hash('sha256', $body)]; }
    file_put_contents($root . '/build-manifest.json', json_encode(['artifact' => 'test', 'files' => $entries], JSON_THROW_ON_ERROR));
    if ($check($root) !== 'PASS') { $fail('an intact client did not pass'); }
    file_put_contents($root . '/client.js', "console.log('changed');\n");
    if ($check($root) !== 'FAIL') { $fail('a modified client file passed'); }
    unlink($root . '/client.js');
    if ($check($root) !== 'FAIL') { $fail('a missing client file passed'); }
    file_put_contents($root . '/client.js', $files['client.js']);
    file_put_contents($root . '/build-manifest.json', json_encode(['files' => [['file' => '../config.json', 'bytes' => 1, 'sha256' => str_repeat('0', 64)]]], JSON_THROW_ON_ERROR));
    if ($check($root) !== 'FAIL') { $fail('a path outside the client directory was accepted'); }
    $real = dirname(__DIR__, 2) . '/web-local-search-client/dist';
    if (is_file($real . '/build-manifest.json') && $check($real) !== 'PASS') { $fail('the built client in this checkout differs from its manifest'); }
} finally {
    foreach (glob($root . '/*') ?: [] as $file) { unlink($file); } rmdir($root);
}
echo "PASS: doctor's D-028 client asset check: unbuilt, intact, modified, missing and escaping entries\n";
