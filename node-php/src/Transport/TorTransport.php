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
    { return $this->request($onion, $endpoint, null, 200); }
    public function announce(OnionAddress $node, OnionAddress $service): void
    {
        $body = json_encode(['protocol_version' => 1, 'onion' => $service->hostname], JSON_THROW_ON_ERROR);
        $wire = $this->request($node, ProtocolEndpoint::announcement(), $body, 202);
        $reply = \OnionServiceDiscovery\Validation\StrictJson::parse($wire, 1024);
        if (count(get_object_vars($reply)) !== 1 || ($reply->status ?? null) !== 'HINT_RECEIVED_UNCONFIRMED') { throw new TransportException('ANNOUNCEMENT_RESPONSE_INVALID'); }
    }
    private function request(OnionAddress $onion, ProtocolEndpoint $endpoint, ?string $requestBody, int $expectedStatus): string
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
            // The SOCKS connect phase includes Tor's circuit to the Onion, which can take most of the limit on first contact.
            CURLOPT_CONNECTTIMEOUT_MS => $this->timeoutMilliseconds, CURLOPT_TIMEOUT_MS => $this->timeoutMilliseconds,
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
        if ($requestBody !== null) {
            $options[CURLOPT_POST] = true; $options[CURLOPT_POSTFIELDS] = $requestBody;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        try {
            // @phpstan-ignore argument.type (libcurl documents empty NOPROXY to force proxy use; PHPStan's non-empty-string stub is inaccurate.)
            if (!curl_setopt_array($handle, $options)) { throw new TransportException('TRANSPORT_UNAVAILABLE'); }
            $success = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($rejected !== null) { throw new TransportException($rejected); }
            // Typed failures tell the operator what to fix (spec 47 section 16): the local Tor SOCKS listener,
            // the Onion behind Tor (SOCKS reply failure), or the time limit.
            if ($success === false) {
                throw new TransportException(match (curl_errno($handle)) {
                    CURLE_COULDNT_RESOLVE_PROXY, CURLE_COULDNT_CONNECT => 'TOR_PROXY_UNREACHABLE',
                    CURLE_PROXY => 'ONION_UNREACHABLE',
                    CURLE_OPERATION_TIMEDOUT => 'TOR_REQUEST_TIMEOUT',
                    default => 'TOR_REQUEST_FAILED',
                });
            }
            // A rate-limited announcement is reported separately so the publisher can show it (spec 47 section 23).
            if ($status === 429 && $requestBody !== null) { throw new TransportException('ANNOUNCEMENT_RATE_LIMITED'); }
            if ($status !== $expectedStatus) {
                throw new TransportException(match (true) {
                    $status === 404 => 'HTTP_NOT_FOUND',
                    $status >= 300 && $status < 400 => 'HTTP_REDIRECT_REJECTED',
                    $status >= 500 && $status < 600 => 'HTTP_SERVER_ERROR',
                    default => 'HTTP_STATUS_REJECTED',
                });
            }
            if (isset($headers['content-encoding']) && strtolower($headers['content-encoding']) !== 'identity') { throw new TransportException('CONTENT_ENCODING_REJECTED'); }
            if (preg_match('/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/iD', $headers['content-type'] ?? '') !== 1) { throw new TransportException('CONTENT_TYPE_REJECTED'); }
            return $body;
        } finally { unset($handle); }
    }
}
