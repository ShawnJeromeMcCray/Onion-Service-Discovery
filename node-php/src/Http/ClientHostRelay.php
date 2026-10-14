<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Http;
use OnionServiceDiscovery\Configuration\NodeConfig;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Transport\ProtocolEndpoint;

/** Query-independent generic data relay; no query, pin, user ID or Service URL input. */
final readonly class ClientHostRelay
{
    public function __construct(private NodeConfig $config) {}

    /** True when the target names a Node the operator did not configure (visitor-chosen source). */
    public function isVisitorSource(string $target): bool
    {
        return preg_match('~\A/onion-service-discovery/relay/([a-z2-7]{56}\.onion)/~', $target, $match) === 1 && !in_array($match[1], $this->config->sources, true);
    }
    /** @return array{status:int,headers:array<string,string>,body:string} */
    public function handle(string $method,string $target): array
    {
        $headers=['Content-Type'=>'application/json; charset=utf-8','Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer','X-Content-Type-Options'=>'nosniff'];
        if(!in_array($method,['GET','HEAD'],true))return ['status'=>405,'headers'=>$headers+['Allow'=>'GET, HEAD'],'body'=>$method==='HEAD'?'':'{"error":"METHOD_NOT_ALLOWED"}'];
        if(!in_array($this->config->role,['client-host','combined'],true)||preg_match('~\A/onion-service-discovery/relay/([a-z2-7]{56}\.onion)/(node|state|snapshots/[0-9a-f]{64}|chunks/[0-9a-f]{64})\z~D',$target,$match)!==1||(!in_array($match[1],$this->config->sources,true)&&!$this->visitorSourceAllowed($match[1])))return ['status'=>404,'headers'=>$headers,'body'=>$method==='HEAD'?'':'{"error":"NOT_FOUND"}'];
        $suffix=$match[2];
        $endpoint=match(true){$suffix==='node'=>ProtocolEndpoint::node(),$suffix==='state'=>ProtocolEndpoint::state(),str_starts_with($suffix,'snapshots/')=>ProtocolEndpoint::snapshot('sha256:'.substr($suffix,10)),default=>ProtocolEndpoint::chunk('sha256:'.substr($suffix,7))};
        $wire=$this->config->transport()->get(OnionAddress::parse($match[1]),$endpoint);
        return ['status'=>200,'headers'=>$headers+['Content-Length'=>(string)strlen($wire)],'body'=>$method==='HEAD'?'':$wire];
    }
    /** Visitor-chosen Nodes need the explicit operator switch and a checksum-valid canonical v3 address. */
    private function visitorSourceAllowed(string $onion): bool
    {
        if (!($this->config->features['visitor_sources'] ?? false)) { return false; }
        try { return OnionAddress::parse($onion)->hostname === $onion; } catch (\InvalidArgumentException) { return false; }
    }
}
