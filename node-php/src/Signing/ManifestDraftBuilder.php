<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Signing;
use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Crypto\Encoding;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Protocol\RecordFormat;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\ValidatedRecord;

final readonly class ManifestDraftBuilder
{
    public function __construct(private RecordVerifier $verifier) {}
    public function genesis(OnionAddress $onion,string $publicKey,\stdClass $metadata,?int $now=null): \stdClass
    {
        Encoding::publicKey($publicKey); $this->metadata($metadata);
        $record=(object)['protocol'=>'Onion Service Discovery','protocol_version'=>1,'record_type'=>'service_manifest','onion'=>$onion->hostname,'service_identity_public_key'=>$publicKey,'revision'=>1,'previous_manifest_hash'=>null,
            'discovery'=>$metadata->discovery??(object)['publish_service'=>false,'mirror_directory'=>false,'discover_services'=>false,'sync_with_peers'=>false],
            'service'=>$metadata->service,'links'=>$metadata->links??(object)['related_onion_services'=>[],'clearnet'=>[]],'published_at'=>$this->timestamp($now)];
        return $this->draft($record);
    }
    public function revise(ValidatedRecord $current,\stdClass $metadata,?int $now=null): \stdClass
    {
        $record=$this->manifest($current); $this->metadata($metadata); $changed=false;
        foreach(['service','links','discovery'] as $field) {
            if(isset($metadata->{$field})&&CanonicalJson::encode($metadata->{$field})!==CanonicalJson::encode($record->{$field})) { $record->{$field}=$metadata->{$field}; $changed=true; }
        }
        if(!$changed) { return $current->document(); }
        $this->successor($record,$current,$now); return $this->draft($record);
    }
    public function withdraw(ValidatedRecord $current,?int $now=null): \stdClass
    {
        $record=$this->manifest($current);
        if(!$record->discovery->publish_service) { return $current->document(); }
        $record->discovery->publish_service=false; $this->successor($record,$current,$now); return $this->draft($record);
    }
    /** @return array{rotation_record:\stdClass,successor_manifest:\stdClass} */
    public function rotate(ValidatedRecord $current,string $nextKey,?int $now=null): array
    {
        Encoding::publicKey($nextKey); $old=$this->manifest($current);
        if($old->service_identity_public_key===$nextKey) { throw new ProtocolException('KEY_ROTATION_INVALID'); }
        $successor=clone $old; $successor->service_identity_public_key=$nextKey; $this->successor($successor,$current,$now);
        $proof=(object)['protocol'=>'Onion Service Discovery','protocol_version'=>1,'record_type'=>'service_identity_key_rotation','onion'=>$old->onion,'old_public_key'=>$old->service_identity_public_key,'new_public_key'=>$nextKey,'effective_revision'=>$successor->revision,'previous_state_hash'=>$current->contentHash];
        return ['rotation_record'=>$this->draft($proof),'successor_manifest'=>$this->draft($successor)];
    }
    /** @return array{migration_record:\stdClass,successor_manifest:\stdClass} */
    public function migrate(ValidatedRecord $current,OnionAddress $nextOnion,?int $now=null): array
    {
        $old=$this->manifest($current); if($old->onion===$nextOnion->hostname) { throw new ProtocolException('MIGRATION_INVALID'); }
        $successor=clone $old; $successor->onion=$nextOnion->hostname; $this->successor($successor,$current,$now);
        $proof=(object)['protocol'=>'Onion Service Discovery','protocol_version'=>1,'record_type'=>'onion_service_migration','service_identity_public_key'=>$old->service_identity_public_key,'from_onion'=>$old->onion,'to_onion'=>$nextOnion->hostname,'effective_revision'=>$successor->revision,'previous_state_hash'=>$current->contentHash];
        return ['migration_record'=>$this->draft($proof),'successor_manifest'=>$this->draft($successor)];
    }
    private function manifest(ValidatedRecord $record): \stdClass { if($record->type!=='service_manifest') { throw new ProtocolException('SCHEMA_INVALID'); } return $record->document(); }
    private function successor(\stdClass $record,ValidatedRecord $current,?int $now): void
    { if($record->revision>=9007199254740991) { throw new ProtocolException('REVISION_EXHAUSTED'); } ++$record->revision; $record->previous_manifest_hash=$current->contentHash; $record->published_at=$this->timestamp($now); }
    private function draft(\stdClass $record): \stdClass
    { foreach(RecordFormat::TYPES[$record->record_type]['excluded'] as $slot) { $record->{$slot}=Encoding::encode(str_repeat("\0",64),'ed25519sig:'); } $this->verifier->assertStructure($record); return $record; }
    private function metadata(\stdClass $metadata): void
    { if(!isset($metadata->service)||array_diff(array_keys(get_object_vars($metadata)),['service','links','discovery'])!==[]) { throw new ProtocolException('METADATA_INVALID'); } }
    private function timestamp(?int $now): string { $now??=time(); if($now<0||$now>253402300799) { throw new ProtocolException('CLOCK_INVALID'); } return gmdate('Y-m-d\TH:i:s\Z',$now); }
}
