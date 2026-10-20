import { decomposeV1 } from './search-unicode.ts';
import { searchUnicodeData } from './search-unicode-data.ts';
import { confusablesData } from './confusables-data.ts';

type Ranges=readonly (readonly [number,number])[];
function inRanges(point:number,ranges:Ranges):boolean {
  let lo=0,hi=ranges.length-1;
  while(lo<=hi){const mid=(lo+hi)>>>1;const range=ranges[mid]!;if(point<range[0])hi=mid-1;else if(point>range[1])lo=mid+1;else return true;}
  return false;
}
function codePoints(value:string):number[] {return Array.from(value,character=>character.codePointAt(0)!);}
function text(points:readonly number[]):string {return points.map(point=>String.fromCodePoint(point)).join('');}
let composites:ReadonlySet<number>|null=null;
/** NFD applies a table decomposition only when it is canonical: a primary composite or a listed one. */
function canonical(point:number):boolean {
  composites??=new Set<number>(Object.values(searchUnicodeData.composition));
  return composites.has(point)||inRanges(point,confusablesData.canonical_not_composed);
}
const DIGITS='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
let prototypes:ReadonlyMap<number,readonly number[]>|null=null;
/** The MA prototypes, decoded on first use; the encoding is described in confusables-data.ts. */
function prototypeTable():ReadonlyMap<number,readonly number[]> {
  if(prototypes!==null)return prototypes;
  const encoded=confusablesData.prototypes;const table=new Map<number,readonly number[]>();let position=0;
  const next=():number=>{
    let value=0,scale=1;
    for(;;){const digit=position<encoded.length?DIGITS.indexOf(encoded[position++]!):-1;if(digit<0)throw new Error('CONFUSABLE_DATA_INVALID');value+=(digit&31)*scale;scale*=32;if(digit<32)return value;}
  };
  for(let source=-1;position<encoded.length;){source+=next()+1;const target:number[]=[];for(let length=next()+1;length>0;--length)target.push(next());table.set(source,target);}
  if(table.size!==confusablesData.prototype_count)throw new Error('CONFUSABLE_DATA_INVALID');
  return prototypes=table;
}
/**
 * Frozen Unicode 17 NFD from the client's own tables, the same in every browser. The shared search
 * table already holds every decomposition; the confusable data adds which of them are canonical.
 */
export function nfdV1(value:string):string {
  if(value.length>65536)throw new Error('SEARCH_WORK_LIMIT');
  return text(decomposeV1(codePoints(value),canonical));
}
/**
 * UTS #39 section 4 skeleton (D-024): NFD, Default_Ignorable_Code_Point removed, every code point
 * replaced by its MA prototype from confusables.txt, NFD again. Two strings are confusable when their
 * skeletons are equal. NFD comes from the frozen Unicode 17 tables (nfdV1), not from
 * String.prototype.normalize: browsers ship different Unicode versions, and whether a look-alike
 * warning appears must not depend on the visitor's browser. A skeleton is a comparison key only: it
 * is never shown, stored or signed, and never replaces a name.
 */
export function skeletonV1(value:string):string {
  if(value.length>65536)throw new Error('SEARCH_WORK_LIMIT');
  const table=prototypeTable();const mapped:number[]=[];
  for(const point of decomposeV1(codePoints(value),canonical)) {
    if(inRanges(point,confusablesData.default_ignorable))continue;
    const prototype=table.get(point);if(prototype===undefined)mapped.push(point);else for(const part of prototype)mapped.push(part);
  }
  return text(decomposeV1(mapped,canonical));
}
