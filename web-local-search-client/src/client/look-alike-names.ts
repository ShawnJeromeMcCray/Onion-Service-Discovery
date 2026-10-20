import { searchTextV1 } from './search-unicode.ts';
import { skeletonV1 } from './confusables.ts';
import { nameKey, type LocalPin, type ServiceRow } from './view-model.ts';

/**
 * Comparison forms of a self-declared name (decision D-024). `same` is the existing same-name form
 * (nameKey: case, compatibility forms, invisible characters and spacing ignored). Two names look alike
 * when they are not the same name but share `visual` or `folded`:
 * - `visual` is the UTS #39 skeleton with case ignored afterwards, so "PaypaI" with a capital I
 *   looks like "paypal";
 * - `folded` is the skeleton of the same-name form, so "MAIL" with a Cyrillic А looks like "mail"
 *   although the skeleton turns a capital I into l but leaves a small i.
 * These are comparison keys only: never shown, stored or signed, and names are never rewritten.
 */
export interface NameForms { same:string; visual:string; folded:string; }
const collapse=(value:string):string=>value.replace(/\s+/gu,' ').trim();
export function nameForms(name:string):NameForms {
  const same=nameKey(name);
  return {same,visual:collapse(searchTextV1(skeletonV1(name))),folded:collapse(skeletonV1(same))};
}
export function looksAlike(a:NameForms,b:NameForms):boolean { return a.same!==b.same&&(a.visual===b.visual||a.folded===b.folded); }
// Keyed by the name, not the row object: the worker sends new row objects with every update, and
// recomputing 10,000 skeletons each time would cost a quarter second or more on the main thread.
const NAME_FORMS_LIMIT=20000;
const formsByName=new Map<string,NameForms>();
function formsOf(row:ServiceRow):NameForms {
  const cached=formsByName.get(row.name); if(cached!==undefined) return cached;
  if(formsByName.size>=NAME_FORMS_LIMIT) formsByName.clear();
  const forms=nameForms(row.name); formsByName.set(row.name,forms); return forms;
}
/** Keys of entries sharing a skeleton form with an entry that is not the same name. Linear: grouped, never paired. */
function lookAlikeKeys(entries:readonly {key:string;forms:NameForms}[]):Set<string> {
  const groups=new Map<string,{names:Set<string>;keys:string[]}>();
  for(const {key,forms} of entries) for(const form of ['v'+forms.visual,'f'+forms.folded]) {
    let group=groups.get(form); if(group===undefined){group={names:new Set(),keys:[]};groups.set(form,group);}
    group.names.add(forms.same); group.keys.push(key);
  }
  const flagged=new Set<string>();
  for(const group of groups.values()) if(group.names.size>1) for(const key of group.keys) flagged.add(key);
  return flagged;
}
/**
 * Identity keys of published entries whose name looks like the name of another published entry, which
 * necessarily has another identity key. Each name is reduced once per view (up to 10,000 rows).
 */
export function lookAlikeEntries(rows:readonly ServiceRow[]):ReadonlySet<string> {
  return lookAlikeKeys(rows.filter(row=>row.status==='PUBLISHED').map(row=>({key:row.genesisKey,forms:formsOf(row)})));
}
/** Identity keys of pins whose label looks like the label of another pin. */
export function lookAlikePins(pins:readonly LocalPin[]):ReadonlySet<string> {
  return lookAlikeKeys(pins.map(pin=>({key:pin.genesisKey,forms:nameForms(pin.label)})));
}
/** A pin whose name a text resembles: `same` when it has the pin's label as its same-name form. */
export interface PinnedNameMatch { label:string; same:boolean; }
/**
 * Compares a name under some identity key with the label and the current name of every pinned
 * identity; a name under a pinned key never matches. A fork has no current name.
 */
function pinnedNames(pins:readonly LocalPin[],rows:readonly ServiceRow[]):(key:string,forms:NameForms)=>PinnedNameMatch|undefined {
  const keys=new Set(pins.map(pin=>pin.genesisKey));
  const names=new Map(rows.filter(row=>keys.has(row.genesisKey)&&row.status!=='FORKED').map(row=>[row.genesisKey,formsOf(row)] as const));
  const sameLabel=new Map<string,string>(); const resembled=new Map<string,string>();
  const remember=(map:Map<string,string>,form:string,label:string)=>{if(!map.has(form))map.set(form,label);};
  for(const pin of pins) {
    const label=nameForms(pin.label); remember(sameLabel,label.same,pin.label);
    for(const forms of [label,names.get(pin.genesisKey)]) if(forms!==undefined) {
      if(forms!==label) remember(resembled,'s'+forms.same,pin.label);
      remember(resembled,'v'+forms.visual,pin.label); remember(resembled,'f'+forms.folded,pin.label);
    }
  }
  return (key,forms)=>{
    if(keys.has(key)) return undefined;
    const label=sameLabel.get(forms.same); if(label!==undefined) return {label,same:true};
    const similar=resembled.get('s'+forms.same)??resembled.get('v'+forms.visual)??resembled.get('f'+forms.folded);
    return similar===undefined?undefined:{label:similar,same:false};
  };
}
/**
 * D-024 next to spec 06 section 22: for an entry under another identity key, the label of the pin
 * whose label or current name the entry's name looks like. Names equal to a pin label stay with the
 * same-name warning (pinNameMatcher), so one entry never gets both.
 */
export function pinLookAlikeMatcher(pins:readonly LocalPin[],rows:readonly ServiceRow[]):(row:ServiceRow)=>string|undefined {
  const match=pinnedNames(pins,rows);
  return row=>{
    if(row.status==='FORKED') return undefined;
    const found=match(row.genesisKey,formsOf(row)); return found!==undefined&&!found.same?found.label:undefined;
  };
}
/** For a pin about to be imported: the held pin under another key whose name its label equals or resembles. */
export function importedPinMatcher(pins:readonly LocalPin[],rows:readonly ServiceRow[]):(pin:LocalPin)=>PinnedNameMatch|undefined {
  const match=pinnedNames(pins,rows);
  return pin=>match(pin.genesisKey,nameForms(pin.label));
}
