<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Http;
use OnionServiceDiscovery\Configuration\NodeConfig;
final readonly class ClientAssets
{
    public function __construct(private NodeConfig $config,private string $directory) {}
    /** @return array{status:int,headers:array<string,string>,body:string}|null */
    public function handle(string $method,string $target): ?array
    {
        if(!in_array($this->config->role,['client-host','combined'],true))return null;
        $files=['/onion-service-discovery/'=>'index.html','/onion-service-discovery/index.html'=>'index.html','/onion-service-discovery/client.js'=>'client.js','/onion-service-discovery/verifier-worker.js'=>'verifier-worker.js','/onion-service-discovery/client.css'=>'client.css','/onion-service-discovery/demo.json'=>'demo.json','/onion-service-discovery/config.json'=>'config.json'];
        if(!isset($files[$target]))return null;
        $headers=['Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer','X-Content-Type-Options'=>'nosniff','X-Frame-Options'=>'DENY','Content-Security-Policy'=>"default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; worker-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'",'Permissions-Policy'=>'camera=(), microphone=(), geolocation=()'];
        if(!in_array($method,['GET','HEAD'],true))return ['status'=>405,'headers'=>$headers+['Content-Type'=>'application/json','Allow'=>'GET, HEAD'],'body'=>'{"error":"METHOD_NOT_ALLOWED"}'];
        $name=$files[$target];
        if($name==='config.json'){
            $body=json_encode(['client_config_version'=>1,'mode'=>$this->config->role==='combined'?'combined':'client-host','allowed_sources'=>$this->config->sources,'minimum_distinct_keys'=>3,'delivery_profile'=>'client-host-proxy'],JSON_THROW_ON_ERROR);$type='application/json';
        }else{
            $path=$this->directory.'/'.$name;
            if(is_link($path)||!is_file($path)||filesize($path)>2097152)return ['status'=>503,'headers'=>$headers+['Content-Type'=>'application/json'],'body'=>'{"error":"CLIENT_BUILD_UNAVAILABLE"}'];
            $body=file_get_contents($path);if($body===false)throw new \RuntimeException('Client asset unavailable');
            $type=match(pathinfo($name,PATHINFO_EXTENSION)){'html'=>'text/html','js'=>'application/javascript','css'=>'text/css',default=>'application/json'};
        }
        return ['status'=>200,'headers'=>$headers+['Content-Type'=>$type.'; charset=utf-8','Content-Length'=>(string)strlen($body)],'body'=>$method==='HEAD'?'':$body];
    }
}
