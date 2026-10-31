import { assertVerifiedRecord, requiredString, type VerifiedRecord } from '../protocol/record-verifier.ts';
import { parseStrictJson } from '../protocol/strict-json.ts';

/** Session-only authenticated Node evidence. Limits fail closed; fork proofs are never evicted. */
export class SourceWitnesses {
  #records=new Map<string,VerifiedRecord>();
  #positions=new Map<string,string>();
  #forks=new Map<string,readonly [VerifiedRecord,VerifiedRecord]>();
  readonly #limit:number;
  readonly #forkLimit:number;
  constructor(limit=512,forkLimit=16){
    if(!Number.isInteger(limit)||limit<1||limit>512||!Number.isInteger(forkLimit)||forkLimit<1||forkLimit>16) throw new Error('WITNESS_CONFIGURATION_INVALID');
    this.#limit=limit;this.#forkLimit=forkLimit;
  }
  get size():number { return this.#records.size+this.#forks.size; }
  clear():void { this.#records.clear();this.#positions.clear();this.#forks.clear(); }
  /** Drops a Node's ordinary witnesses below its accepted anchor, as the PHP Node's LocalMaintenance does: they no longer detect anything. */
  retain(nodeKey:string,generation:number,descriptorRevision:number):void {
    for(const [position,hash] of this.#positions){
      const [type,key,sequence]=position.split('|');
      if(key===nodeKey&&Number(sequence)<(type==='snapshot_descriptor'?generation:descriptorRevision)){this.#positions.delete(position);this.#records.delete(hash);}
    }
  }
  forkEvidence(nodeKey:string):readonly VerifiedRecord[] { return [...(this.#forks.get(nodeKey)??[])]; }
  observe(evidence:VerifiedRecord):boolean {
    assertVerifiedRecord(evidence);
    if(evidence.type!=='snapshot_descriptor'&&evidence.type!=='node_descriptor') throw new Error('WITNESS_TYPE_INVALID');
    const document=parseStrictJson(new TextEncoder().encode(evidence.canonicalWire),65536);
    const key=requiredString(document.node_identity_public_key);
    if(this.#forks.has(key)) return true;
    const sequence=evidence.type==='snapshot_descriptor'?document.generation:document.descriptor_revision;
    const position=evidence.type+'|'+key+'|'+String(sequence);
    const previous=this.#positions.get(position);
    if(previous!==undefined&&previous!==evidence.contentHash){
      if(this.#forks.size>=this.#forkLimit) throw new Error('WITNESS_BUDGET_EXHAUSTED');
      // Reserved space retains both signed records even when normal capacity is full.
      this.#forks.set(key,[this.#records.get(previous)!,evidence]);
      return true;
    }
    if(!this.#records.has(evidence.contentHash)){
      if(this.#records.size>=this.#limit) throw new Error('WITNESS_BUDGET_EXHAUSTED');
      this.#records.set(evidence.contentHash,evidence);this.#positions.set(position,evidence.contentHash);
    }
    return false;
  }
}
