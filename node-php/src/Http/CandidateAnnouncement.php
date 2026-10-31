<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Http;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Node\CandidateAdmission;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Storage\StorageBackend;
use OnionServiceDiscovery\Storage\StorageTransaction;
use OnionServiceDiscovery\Validation\StrictJson;

/** Draft bounded untrusted hint receiver. No transport, verifier or signer capability. */
final readonly class CandidateAnnouncement
{
    public const PATH = '/onion-service-discovery/v1/candidates/announce';
    public const MAX_BYTES = 256;
    public function __construct(private StorageBackend $store) {}
    /** @return array{status:int,headers:array<string,string>,body:string} */
    public function handle(string $method, string $contentType, string $body, int $now): array
    {
        if ($method !== 'POST') { return $this->response(405, 'METHOD_NOT_ALLOWED', ['Allow' => 'POST']); }
        if (preg_match('/\Aapplication\/json(?:;\s*charset=utf-8)?\z/iD', $contentType) !== 1) { return $this->response(415, 'CONTENT_TYPE_REJECTED'); }
        if (strlen($body) > self::MAX_BYTES) { return $this->response(413, 'REQUEST_TOO_LARGE'); }
        try {
            $hint = StrictJson::parse($body, self::MAX_BYTES);
            if (count(get_object_vars($hint)) !== 2 || array_diff(array_keys(get_object_vars($hint)), ['protocol_version','onion']) !== [] || ($hint->protocol_version ?? null) !== 1 || !is_string($hint->onion ?? null)) { throw new ProtocolException('SCHEMA_INVALID'); }
            $address = OnionAddress::parse($hint->onion);
            $admitted = $this->store->transaction(static function (StorageTransaction $tx) use ($address, $now): bool {
                // Every anonymous caller shares one source budget. No supplied identity,
                // IP, cookie or User-Agent can create extra admission capacity.
                $queued = CandidateAdmission::enqueue($tx, $address, 'announcement', $now);
                return $queued || $tx->get('candidates', $address->hostname) !== null;
            });
            if (!$admitted) { return $this->response(429, 'ADMISSION_DEFERRED', ['Retry-After' => '3600']); }
            return $this->response(202, 'HINT_RECEIVED_UNCONFIRMED');
        } catch (\InvalidArgumentException) { return $this->response(400, 'REQUEST_INVALID'); }
    }
    /** @param array<string,string> $extra
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private function response(int $status, string $code, array $extra = []): array
    {
        // One shape per outcome, as on every other route: {"status":...} for the accepted hint only, {"error":CODE} otherwise.
        $body = json_encode([$status === 202 ? 'status' : 'error' => $code], JSON_THROW_ON_ERROR);
        return ['status' => $status, 'headers' => $extra + ['Content-Type' => 'application/json; charset=utf-8', 'Content-Length' => (string)strlen($body), 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'"], 'body' => $body];
    }
}
