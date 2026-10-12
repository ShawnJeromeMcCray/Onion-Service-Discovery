<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Node;
use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\ValidatedRecord;
/** Opt-in active data plus necessary continuity/withdrawal evidence; never private candidates. */
final class PublicEvidencePolicy
{
    /** @param list<ValidatedRecord> $records
     * @return array<string,true>
     */
    public static function select(array $records): array
    {
        $documents=[];foreach($records as $record){$documents[$record->contentHash]=$record->document();}
        $view=IdentityDeriver::derive($records,10000);$selected=[];$keys=[];
        foreach($view['identities'] as $identity){
            $published=false;foreach($identity['history_hashes'] as $hash){if($documents[$hash]->discovery->publish_service){$published=true;break;}}
            if(!$published)continue;
            foreach($identity['history_hashes'] as $hash){$selected[$hash]=true;$keys[$documents[$hash]->service_identity_public_key]=true;}
            foreach(array_merge($identity['rotation_hashes'],$identity['migration_hashes']) as $hash)$selected[$hash]=true;
            foreach($identity['transition_conflicts'] as $conflict){foreach($conflict['record_hashes'] as $hash)$selected[$hash]=true;}
        }
        foreach($records as $record){$doc=$documents[$record->contentHash];
            if($record->type==='node_descriptor')$selected[$record->contentHash]=true;
            elseif($record->type==='service_manifest'&&($doc->discovery->publish_service||isset($keys[$doc->service_identity_public_key])))$selected[$record->contentHash]=true;
        }
        foreach($records as $record){$doc=$documents[$record->contentHash];
            if($record->type==='direct_observation'&&isset($selected[$doc->manifest_hash]))$selected[$record->contentHash]=true;
            elseif(in_array($record->type,['service_identity_key_rotation','onion_service_migration'],true)&&isset($selected[$doc->previous_state_hash]))$selected[$record->contentHash]=true;
        }
        return $selected;
    }
}
