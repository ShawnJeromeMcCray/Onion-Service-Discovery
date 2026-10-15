import { searchTextV1 } from './search-unicode.ts';
import type { ServiceRow } from './view-model.ts';

export interface ResultGroup { representative: ServiceRow; entries: ServiceRow[]; }

/** Presentation grouping only. Never merges identity evidence or implies common ownership. */
export function groupSimilarResults(rows: readonly ServiceRow[]): ResultGroup[] {
  const groups=new Map<string,ResultGroup>();
  for(const row of rows) {
    const text=(value:string)=>searchTextV1(value).replace(/\s+/gu,' ').trim();
    // JSON framing avoids ambiguous concatenation; cryptographic identifiers are not the key.
    const key=JSON.stringify([text(row.name),text(row.description),row.status]);
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
