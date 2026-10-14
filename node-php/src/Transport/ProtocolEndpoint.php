<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Transport;
use OnionServiceDiscovery\Domain\OnionAddress;
/** Closed endpoint constructors prevent peer-controlled arbitrary URL paths. */
final readonly class ProtocolEndpoint
{
    private function __construct(public string $path, public int $maxBytes) {}
    public static function manifest(): self { return new self('/.well-known/onion-service-discovery.json', 16384); }
    /** PCP-004 service history bundle beside the Manifest. */
    public static function history(): self { return new self('/.well-known/onion-service-discovery-history.json', 1048576); }
    public static function announcement(): self { return new self('/onion-service-discovery/v1/candidates/announce', 1024); }
    public static function node(): self { return new self('/onion-service-discovery/v1/node', 16384); }
    public static function state(): self { return new self('/onion-service-discovery/v1/state', 16384); }
    public static function peers(): self { return new self('/onion-service-discovery/v1/peers', 262144); }
    public static function service(OnionAddress $service): self { return new self('/onion-service-discovery/v1/services/' . $service->hostname, 32768); }
    public static function changes(int $after): self
    {
        if ($after < 0 || $after > 9007199254740991) { throw new TransportException('CURSOR_INVALID'); }
        return new self('/onion-service-discovery/v1/changes?after=' . $after, 1048576);
    }
    public static function snapshot(string $hash): self { return new self('/onion-service-discovery/v1/snapshots/' . self::hash($hash), 65536); }
    public static function chunk(string $hash): self { return new self('/onion-service-discovery/v1/chunks/' . self::hash($hash), 524288); }
    private static function hash(string $hash): string
    {
        if (preg_match('/\Asha256:[0-9a-f]{64}\z/D', $hash) !== 1) { throw new TransportException('HASH_INVALID'); }
        return substr($hash, 7);
    }
}
