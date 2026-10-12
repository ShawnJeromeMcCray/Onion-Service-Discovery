<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Signing;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\ValidatedRecord;
use OnionServiceDiscovery\Protocol\ProtocolException;
/** Transfers only a verified public manifest to the web-serving directory. */
final readonly class ManifestInstaller
{
    public function __construct(private RecordVerifier $verifier) {}
    /** @return array{status:string,manifest_hash:string} */
    public function install(ValidatedRecord $manifest,string $hostnamePath,string $webroot,?ValidatedRecord $rotation=null,?ValidatedRecord $migration=null): array
    {
        if($manifest->type!=='service_manifest'||basename($hostnamePath)!=='hostname'||is_link($hostnamePath)||!is_file($hostnamePath)||filesize($hostnamePath)>64)throw new ProtocolException('PUBLIC_HOSTNAME_FILE_REQUIRED');
        $hostname=file_get_contents($hostnamePath);if($hostname===false)throw new ProtocolException('PUBLIC_HOSTNAME_FILE_REQUIRED');$onion=OnionAddress::parse(rtrim($hostname,"\r\n"));$current=$manifest->document();if($current->onion!==$onion->hostname)throw new ProtocolException('ONION_BINDING_MISMATCH');
        if(str_contains($webroot,'://')||is_link($webroot)||!is_dir($webroot))throw new ProtocolException('WEBROOT_INVALID');
        $directory=$webroot.'/.well-known';if(is_link($directory)||(!is_dir($directory)&&!mkdir($directory,0755)))throw new ProtocolException('WEBROOT_INVALID');$path=$directory.'/onion-service-discovery.json';
        $lockPath=$directory.'/.publisher.lock';if(is_link($lockPath))throw new ProtocolException('PUBLIC_MANIFEST_PATH_UNSAFE');
        $mask=umask(0077);try{$lock=fopen($lockPath,'c+b');}finally{umask($mask);}if($lock===false)throw new ProtocolException('PUBLIC_MANIFEST_WRITE_FAILED');
        $deadline=hrtime(true)+2000000000;while(!flock($lock,LOCK_EX|LOCK_NB)){if(hrtime(true)>=$deadline){fclose($lock);throw new ProtocolException('PUBLISHER_BUSY');}usleep(10000);}
        try {
        if(is_link($path))throw new ProtocolException('PUBLIC_MANIFEST_PATH_UNSAFE');
        if(is_file($path)){
            if(filesize($path)>16384)throw new ProtocolException('EXISTING_MANIFEST_INVALID');$bytes=file_get_contents($path);if($bytes===false)throw new ProtocolException('EXISTING_MANIFEST_INVALID');$previous=$this->verifier->verifyWire($bytes);$old=$previous->document();
            if($previous->type!=='service_manifest')throw new ProtocolException('EXISTING_MANIFEST_INVALID');
            if($manifest->contentHash===$previous->contentHash)return ['status'=>'UNCHANGED','manifest_hash'=>$manifest->contentHash];
            if($current->revision!==$old->revision+1||$current->previous_manifest_hash!==$previous->contentHash)throw new ProtocolException('REVISION_CHAIN_BROKEN');
            if($current->service_identity_public_key!==$old->service_identity_public_key){
                if($rotation===null||$rotation->type!=='service_identity_key_rotation')throw new ProtocolException('KEY_ROTATION_INVALID');$r=$rotation->document();
                if($r->old_public_key!==$old->service_identity_public_key||$r->new_public_key!==$current->service_identity_public_key||$r->onion!==$old->onion||$r->effective_revision!==$current->revision||$r->previous_state_hash!==$previous->contentHash)throw new ProtocolException('KEY_ROTATION_INVALID');
            }
            if($current->onion!==$old->onion){
                if($migration===null||$migration->type!=='onion_service_migration')throw new ProtocolException('MIGRATION_INVALID');$m=$migration->document();
                if($m->service_identity_public_key!==$old->service_identity_public_key||$m->from_onion!==$old->onion||$m->to_onion!==$current->onion||$m->effective_revision!==$current->revision||$m->previous_state_hash!==$previous->contentHash)throw new ProtocolException('MIGRATION_INVALID');
            }
        }
        $temporary=$directory.'/.manifest-'.bin2hex(random_bytes(12));$mask=umask(0077);try{$stream=fopen($temporary,'x+b');}finally{umask($mask);}if($stream===false)throw new ProtocolException('PUBLIC_MANIFEST_WRITE_FAILED');
        try{$wire=$manifest->canonicalWire;$offset=0;while($offset<strlen($wire)){$written=fwrite($stream,substr($wire,$offset));if($written===false||$written<1)throw new ProtocolException('PUBLIC_MANIFEST_WRITE_FAILED');$offset+=$written;}if(!fflush($stream)||!fsync($stream)||!chmod($temporary,0644))throw new ProtocolException('PUBLIC_MANIFEST_WRITE_FAILED');}catch(\Throwable $error){@unlink($temporary);throw $error;}finally{fclose($stream);}
        if(!rename($temporary,$path)){@unlink($temporary);throw new ProtocolException('PUBLIC_MANIFEST_WRITE_FAILED');}$handle=fopen($directory,'r');if($handle===false)throw new ProtocolException('FILESYSTEM_SEMANTICS_UNSUPPORTED');try{if(!fsync($handle))throw new ProtocolException('FILESYSTEM_SEMANTICS_UNSUPPORTED');}finally{fclose($handle);}
        return ['status'=>'INSTALLED','manifest_hash'=>$manifest->contentHash];
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }
}
