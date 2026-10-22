import { searchTextV1 } from './search-unicode.ts';
import type { ServiceRow } from './view-model.ts';

export interface ResultGroup { representative: ServiceRow; entries: ServiceRow[]; }

/** Presentation grouping only. Never merges identity evidence or implies common ownership. */
// Normalizing every row on every render costs seconds without a JIT; the key depends only on the row's text.
const GROUP_KEY_CACHE_LIMIT=12000; const groupKeys=new Map<string,string>();
function groupKey(row:ServiceRow):string {
  // JSON framing avoids ambiguous concatenation; cryptographic identifiers are not the key.
  const raw=JSON.stringify([row.name,row.description,row.status]); const cached=groupKeys.get(raw);
  if(cached!==undefined)return cached;
  if(groupKeys.size>=GROUP_KEY_CACHE_LIMIT)groupKeys.clear();
  const text=(value:string)=>searchTextV1(value).replace(/\s+/gu,' ').trim();
  const key=JSON.stringify([text(row.name),text(row.description),row.status]); groupKeys.set(raw,key);
  return key;
}
export function groupSimilarResults(rows: readonly ServiceRow[]): ResultGroup[] {
  const groups=new Map<string,ResultGroup>();
  for(const row of rows) {
    const key=groupKey(row);
    const group=groups.get(key);
    if(group===undefined)groups.set(key,{representative:row,entries:[row]});
    else group.entries.push(row);
  }
  return [...groups.values()];
}
/** The identity you pinned leads its group, whatever another key's name sorts like. */
export function groupLead(group:ResultGroup,pinned:ReadonlySet<string>):ServiceRow {
  return group.entries.find(entry=>pinned.has(entry.genesisKey))??group.representative;
}
