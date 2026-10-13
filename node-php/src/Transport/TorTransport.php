<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Transport;
use OnionServiceDiscovery\Domain\OnionAddress;

/** Single outbound HTTP path: hostname resolution and connection through SOCKS5. */
final readonly class TorTransport
{
    public function __construct(private string $proxyHost = '127.0.0.1', private int $proxyPort = 9050, private int $timeoutMilliseconds = 30000)
    {
        if (!extension_loaded('curl') || !in_array($proxyHost, ['127.0.0.1', '::1'], true) || $proxyPort < 1 || $proxyPort > 65535 || $timeoutMilliseconds < 100 || $timeoutMilliseconds > 60000) { throw new TransportException('TRANSPORT_CONFIGURATION_INVALID'); }
    }
    public function get(OnionAddress $onion, ProtocolEndpoint $endpoint): string
    {
        $handle = curl_init();
        if ($handle === false) { throw new TransportException('TRANSPORT_UNAVAILABLE'); }
        $body = ''; $headers = []; $headerBytes = 0; $rejected = null;
        // Tor SOCKS extension format 0: a fresh per-request isolation parameter.
        // This is not an end-user identifier and is never persisted.
        $isolation = bin2hex(random_bytes(16));
        $options = [
            CURLOPT_URL => 'http://' . $onion->hostname . $endpoint->path,
            CURLOPT_PROXY => $this->proxyHost, CURLOPT_PROXYPORT => $this->proxyPort,
            CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
            CURLOPT_PROXYUSERNAME => '<torS0X>0', CURLOPT_PROXYPASSWORD => $isolation,
            CURLOPT_NOPROXY => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_CONNECTTIMEOUT_MS => min(10000, $this->timeoutMilliseconds), CURLOPT_TIMEOUT_MS => $this->timeoutMilliseconds,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_HTTP_CONTENT_DECODING => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Encoding: identity', 'Connection: close'],
            CURLOPT_USERAGENT => 'Onion-Service-Discovery/1', CURLOPT_NETRC => CURL_NETRC_IGNORED,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$rejected, $endpoint): int {
                if (strlen($body) + strlen($chunk) > $endpoint->maxBytes) { $rejected = 'RESPONSE_TOO_LARGE'; return 0; }
                $body .= $chunk; return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers, &$headerBytes, &$rejected, $endpoint): int {
                $headerBytes += strlen($line);
                if ($headerBytes > 8192 || strlen($line) > 2048) { $rejected = 'HEADERS_TOO_LARGE'; return 0; }
                if (preg_match('/\AHTTP\/1\.[01] [0-9]{3}/', $line) === 1) { $headers = []; }
                elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2); $name = strtolower(trim($name));
                    if (isset($headers[$name]) && in_array($name, ['content-type', 'content-length', 'content-encoding'], true)) { $rejected = 'HEADERS_INVALID'; return 0; }
                    $headers[$name] = trim($value);
                    if ($name === 'content-length' && (preg_match('/\A(0|[1-9][0-9]{0,6})\z/D', $headers[$name]) !== 1 || (int) $headers[$name] > $endpoint->maxBytes)) { $rejected = 'RESPONSE_TOO_LARGE'; return 0; }
                    if ($name === 'content-encoding' && strtolower($headers[$name]) !== 'identity') { $rejected = 'CONTENT_ENCODING_REJECTED'; return 0; }
                }
                return strlen($line);
            },
        ];
        try {
            // @phpstan-ignore argument.type (libcurl documents empty NOPROXY to force proxy use; PHPStan's non-empty-string stub is inaccurate.)
            if (!curl_setopt_array($handle, $options)) { throw new TransportException('TRANSPORT_UNAVAILABLE'); }
            $success = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($rejected !== null) { throw new TransportException($rejected); }
            if ($success === false) { throw new TransportException('TOR_REQUEST_FAILED'); }
            if ($status !== 200) { throw new TransportException('HTTP_STATUS_REJECTED'); }
            if (isset($headers['content-encoding']) && strtolower($headers['content-encoding']) !== 'identity') { throw new TransportException('CONTENT_ENCODING_REJECTED'); }
            if (preg_match('/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/iD', $headers['content-type'] ?? '') !== 1) { throw new TransportException('CONTENT_TYPE_REJECTED'); }
            return $body;
        } finally { unset($handle); }
    }
}
