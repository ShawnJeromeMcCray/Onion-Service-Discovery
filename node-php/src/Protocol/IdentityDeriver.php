<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Protocol;

/** Deterministic evidence graph. Observations, timestamps and source counts never choose identity. */
final class IdentityDeriver
{
    /**
     * @param list<mixed> $records
     * @return array<array-key, mixed>
     */
    public static function derive(array $records, int $maxRecords = 100000): array
    {
        if ($maxRecords < 1 || $maxRecords > 1000000 || count($records) > $maxRecords) {
            throw new ProtocolException('WORK_BUDGET_EXHAUSTED');
        }
        $manifests = [];
        $children = [];
        $rotations = [];
        $migrations = [];
        $rotationTargets = [];
        $migrationTargets = [];
        foreach ($records as $record) {
            if (!$record instanceof ValidatedRecord) {
                throw new ProtocolException('SCHEMA_INVALID');
            }
            $document = $record->document();
            if ($record->type === 'service_manifest') {
                $manifests[$record->contentHash] = $document;
                if ($document->previous_manifest_hash !== null) {
                    $children[$document->previous_manifest_hash][$record->contentHash] = true;
                }
            } elseif ($record->type === 'service_identity_key_rotation') {
                $index = self::transitionKey($document->previous_state_hash, $document->effective_revision, $document->onion, $document->old_public_key, $document->new_public_key);
                $rotations[$index][$record->contentHash] = true;
                $context = self::transitionKey($document->previous_state_hash, $document->effective_revision, $document->onion, $document->old_public_key, '');
                $rotationTargets[$context][$document->new_public_key][$record->contentHash] = true;
            } elseif ($record->type === 'onion_service_migration') {
                $index = self::transitionKey($document->previous_state_hash, $document->effective_revision, $document->from_onion, $document->to_onion, $document->service_identity_public_key);
                $migrations[$index][$record->contentHash] = true;
                $context = self::transitionKey($document->previous_state_hash, $document->effective_revision, $document->from_onion, $document->service_identity_public_key, '');
                $migrationTargets[$context][$document->to_onion][$record->contentHash] = true;
            }
        }
        ksort($manifests, SORT_STRING);
        $queue = [];
        $ownership = [];
        /** @var array<string, array{genesis_key:string,highest_revision:int,revisions:array<int,array<string,true>>,rotation_hashes:list<string>,migration_hashes:list<string>,conflicts:list<array{revision:int,hashes:list<string>}>,transition_conflicts:array<string,array{type:string,previous_hash:string,record_hashes:list<string>}>}> $states */
        $states = [];
        foreach ($manifests as $hash => $manifest) {
            if ($manifest->revision !== 1) {
                continue;
            }
            $identity = $manifest->service_identity_public_key;
            $ownership[$hash] = $identity;
            $states[$identity] ??= ['genesis_key' => $identity, 'highest_revision' => 0, 'revisions' => [], 'rotation_hashes' => [], 'migration_hashes' => [], 'conflicts' => [], 'transition_conflicts' => []];
            $queue[] = $hash;
        }
        $seen = [];
        $rejected = [];
        for ($position = 0; $position < count($queue); ++$position) {
            $hash = $queue[$position];
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;
            $current = $manifests[$hash];
            $identity = $ownership[$hash];
            if (!isset($states[$identity])) { throw new \LogicException('Missing genesis state'); }
            $identityState = $states[$identity];
            $identityState['revisions'][$current->revision][$hash] = true;
            $identityState['highest_revision'] = max($identityState['highest_revision'], $current->revision);
            if ($current->revision < 9007199254740991) {
                $context = self::transitionKey($hash, $current->revision + 1, $current->onion, $current->service_identity_public_key, '');
                foreach (['KEY_ROTATION_CONFLICT' => $rotationTargets[$context] ?? [], 'MIGRATION_CONFLICT' => $migrationTargets[$context] ?? []] as $kind => $targets) {
                    if (count($targets) > 1) {
                        $proofs = [];
                        foreach ($targets as $hashes) {
                            $proofs = array_merge($proofs, array_keys($hashes));
                        }
                        sort($proofs, SORT_STRING);
                        $identityState['transition_conflicts'][$kind . '|' . $hash] = ['type' => $kind, 'previous_hash' => $hash, 'record_hashes' => $proofs];
                    }
                }
            }
            $nextHashes = array_keys($children[$hash] ?? []);
            sort($nextHashes, SORT_STRING);
            foreach ($nextHashes as $nextHash) {
                $next = $manifests[$nextHash];
                if ($current->revision === 9007199254740991 || $next->revision !== $current->revision + 1) {
                    $rejected[$nextHash] = 'REVISION_CHAIN_BROKEN';
                    continue;
                }
                $rotation = [];
                if ($next->service_identity_public_key !== $current->service_identity_public_key) {
                    $key = self::transitionKey($hash, $next->revision, $current->onion, $current->service_identity_public_key, $next->service_identity_public_key);
                    $rotation = array_keys($rotations[$key] ?? []);
                    if ($rotation === []) {
                        $rejected[$nextHash] = 'KEY_ROTATION_INVALID';
                        continue;
                    }
                }
                $migration = [];
                if ($next->onion !== $current->onion) {
                    $key = self::transitionKey($hash, $next->revision, $current->onion, $next->onion, $current->service_identity_public_key);
                    $migration = array_keys($migrations[$key] ?? []);
                    if ($migration === []) {
                        $rejected[$nextHash] = 'MIGRATION_INVALID';
                        continue;
                    }
                }
                $identityState['rotation_hashes'] = array_merge($identityState['rotation_hashes'], $rotation);
                $identityState['migration_hashes'] = array_merge($identityState['migration_hashes'], $migration);
                $ownership[$nextHash] = $identity;
                $queue[] = $nextHash;
            }
            $states[$identity] = $identityState;
        }
        $results = [];
        ksort($states, SORT_STRING);
        foreach ($states as $identity => $state) {
            $history = [];
            ksort($state['revisions'], SORT_NUMERIC);
            foreach ($state['revisions'] as $revision => $hashes) {
                $hashList = array_keys($hashes);
                sort($hashList, SORT_STRING);
                foreach ($hashList as $hash) {
                    $history[] = $hash;
                }
                if (count($hashList) > 1) {
                    $state['conflicts'][] = ['revision' => $revision, 'hashes' => $hashList];
                }
            }
            $highest = array_keys($state['revisions'][$state['highest_revision']]);
            ksort($state['transition_conflicts'], SORT_STRING);
            $forked = $state['conflicts'] !== [] || $state['transition_conflicts'] !== [];
            $currentHash = $forked ? null : $highest[0];
            $current = $currentHash === null ? null : $manifests[$currentHash];
            $rotationHashes = array_values(array_unique($state['rotation_hashes']));
            $migrationHashes = array_values(array_unique($state['migration_hashes']));
            sort($rotationHashes, SORT_STRING);
            sort($migrationHashes, SORT_STRING);
            $results[] = [
                'genesis_key' => $identity,
                'highest_revision' => $state['highest_revision'],
                'identity_state' => $forked ? 'FORKED' : 'VALID',
                'current_hash' => $currentHash,
                'current_manifest' => $current,
                'discoverability' => $current === null ? 'UNKNOWN' : ($current->discovery->publish_service ? 'PUBLISHED' : 'WITHDRAWN'),
                'history_hashes' => $history,
                'rotation_hashes' => $rotationHashes,
                'migration_hashes' => $migrationHashes,
                'conflicts' => $state['conflicts'],
                'transition_conflicts' => array_values($state['transition_conflicts']),
            ];
        }
        $pending = [];
        foreach ($manifests as $hash => $manifest) {
            if (!isset($seen[$hash]) && !isset($rejected[$hash])) {
                $pending[] = $hash;
            }
        }
        sort($pending, SORT_STRING);
        ksort($rejected, SORT_STRING);
        return ['identities' => $results, 'pending_hashes' => $pending, 'rejected' => (object) $rejected];
    }

    private static function transitionKey(string $hash, int $revision, string $a, string $b, string $c): string
    {
        return $hash . '|' . $revision . '|' . $a . '|' . $b . '|' . $c;
    }
}
