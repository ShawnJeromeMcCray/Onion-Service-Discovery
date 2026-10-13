<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Search;
use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\ValidatedRecord;

/** Version-1 presentation semantics shared with the browser, no network/state authority.
 * @phpstan-type ServiceRow array{genesisKey:string,name:string,description:string,onion:?string,revision:int,status:'PUBLISHED'|'WITHDRAWN'|'FORKED',addressConflict:bool,languages:list<string>,categories:list<string>,observationClaims:int,rotationCount:int,migrationCount:int,currentHash:?string,searchText:string}
 */
final class LocalSearch
{
    /** @param list<ValidatedRecord> $records
     * @return list<ServiceRow>
     */
    public static function build(array $records): array
    {
        $view = IdentityDeriver::derive($records, 10000); $documents = []; $claims = [];
        foreach ($records as $record) {
            $doc = $record->document(); $documents[$record->contentHash] = $doc;
            if ($record->type === 'direct_observation') { $claims[$doc->manifest_hash][$doc->node_identity_public_key] = true; }
        }
        $rows = []; $owners = [];
        foreach ($view['identities'] as $identity) {
            $current = $identity['current_manifest']; $historical = $documents[$identity['history_hashes'][0] ?? ''] ?? null;
            $onion = $current === null ? ($historical === null ? null : $historical->onion) : $current->onion;
            if ($onion !== null) { $owners[$onion][$identity['genesis_key']] = true; }
            $service = $current?->service;
            $row = ['genesisKey' => $identity['genesis_key'], 'name' => $service === null ? 'Conflicting identity history' : $service->name,
                'description' => $service === null ? 'Multiple signed branches exist. No current manifest is selected.' : ($service->description ?? ''),
                'onion' => $onion, 'revision' => $identity['highest_revision'],
                'status' => $identity['identity_state'] === 'FORKED' ? 'FORKED' : ($identity['discoverability'] === 'PUBLISHED' ? 'PUBLISHED' : 'WITHDRAWN'),
                'addressConflict' => false, 'languages' => $service === null ? [] : $service->languages, 'categories' => $service === null ? [] : $service->categories,
                'observationClaims' => $identity['current_hash'] === null ? 0 : count($claims[$identity['current_hash']] ?? []),
                'rotationCount' => count($identity['rotation_hashes']), 'migrationCount' => count($identity['migration_hashes']), 'currentHash' => $identity['current_hash']];
            $row['searchText'] = UnicodeSearch::normalize(implode(' ', [$row['name'], $row['description'], $onion ?? '', $row['genesisKey'], ...$row['categories'], ...$row['languages']]));
            $rows[] = $row;
        }
        foreach ($rows as &$row) { $row['addressConflict'] = $row['onion'] !== null && count($owners[$row['onion']] ?? []) > 1; } unset($row);
        return $rows;
    }
    /** @param list<ServiceRow> $rows
     * @return list<ServiceRow>
     */
    public static function filter(array $rows, string $query, string $language = '', string $category = '', string $adult = 'show', ?\Closure $budget = null): array
    {
        // Form parsing enforces 256 UTF-16 units; browser uses the same prefix bound.
        $units = 0; foreach (UnicodeSearch::points($query) as $point) { $units += $point > 0xffff ? 2 : 1; }
        if ($units > 256 || !in_array($adult, ['show','de-emphasize','hide'], true)) { throw new \InvalidArgumentException('SEARCH_REQUEST_INVALID'); }
        $normalized = UnicodeSearch::normalize($query);
        // ECMAScript whitespace, frozen explicitly (not runtime-dependent PCRE \s).
        $terms = preg_split('/[\x{0009}-\x{000d}\x{0020}\x{00a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}\x{feff}]+/u', $normalized);
        if ($terms === false) { throw new \InvalidArgumentException('SEARCH_REQUEST_INVALID'); }
        $terms = array_slice(array_values(array_filter($terms, static fn (string $term): bool => $term !== '')), 0, 16); $results = [];
        foreach ($rows as $row) {
            if ($budget !== null) { $budget(); }
            if ($row['status'] === 'WITHDRAWN' || ($language !== '' && !in_array($language, $row['languages'], true)) || ($category !== '' && !in_array($category, $row['categories'], true)) || ($adult === 'hide' && in_array('adult', $row['categories'], true))) { continue; }
            $match = true; foreach ($terms as $term) { if (!str_contains($row['searchText'], $term)) { $match = false; break; } }
            if ($match) { $results[] = $row; }
        }
        usort($results, static function (array $a, array $b) use ($adult): int {
            if ($adult === 'de-emphasize') { $difference = (int)in_array('adult', $a['categories'], true) - (int)in_array('adult', $b['categories'], true); if ($difference !== 0) { return $difference; } }
            // JS < compares UTF-16 code units; UTF-8 strcmp would misorder astral names.
            return strcmp((string)iconv('UTF-8', 'UTF-16BE', $a['name']), (string)iconv('UTF-8', 'UTF-16BE', $b['name'])) ?: strcmp($a['genesisKey'], $b['genesisKey']);
        });
        if ($budget !== null) { $budget(); }
        return $results;
    }
}
