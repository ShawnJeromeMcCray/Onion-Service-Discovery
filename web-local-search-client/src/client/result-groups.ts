import type { ServiceRow } from './view-model.ts';

export interface ResultGroup { representative: ServiceRow; entries: ServiceRow[]; }

/** Presentation grouping only. Never merges identity evidence or implies common ownership. */
export function groupSimilarResults(rows: readonly ServiceRow[]): ResultGroup[] {
  const groups=new Map<string,ResultGroup>();
  for(const row of rows) {
    const text=(value:string)=>value.normalize('NFKC').toLowerCase().replace(/\s+/gu,' ').trim();
    // JSON framing avoids ambiguous concatenation; cryptographic identifiers are not the key.
    const key=JSON.stringify([text(row.name),text(row.description),row.status]);
    const group=groups.get(key);
    if(group===undefined)groups.set(key,{representative:row,entries:[row]});
    else group.entries.push(row);
  }
  return [...groups.values()];
}
