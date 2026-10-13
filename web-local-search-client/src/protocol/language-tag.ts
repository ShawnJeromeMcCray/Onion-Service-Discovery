import { LANGUAGE_GRAMMAR, GRANDFATHERED_TAGS } from './language-grammar.ts';
const grammar=new RegExp('^(?:'+LANGUAGE_GRAMMAR+')$');
const variant=/^(?:[a-z0-9]{5,8}|[0-9][a-z0-9]{3})$/;

export function validateLanguageTag(tag:string):void {
  if(tag.length>35||grammar.exec(tag)?.[0]!==tag)throw new Error('SCHEMA_INVALID');
  if(GRANDFATHERED_TAGS.has(tag)||tag.startsWith('x-'))return;
  const parts=tag.split('-');let index=1;
  if(parts[0]!.length<=3)for(let n=0;n<3&&/^[a-z]{3}$/.test(parts[index]??'');n++)index++;
  if(/^[a-z]{4}$/.test(parts[index]??''))index++;
  if(/^(?:[a-z]{2}|[0-9]{3})$/.test(parts[index]??''))index++;
  const variants=new Set<string>();
  while(variant.test(parts[index]??'')) {
    const value=parts[index++]!;if(variants.has(value))throw new Error('SCHEMA_INVALID');variants.add(value);
  }
  const extensions=new Set<string>();
  while(index<parts.length&&parts[index]!=='x') {
    const singleton=parts[index++]!;
    if(extensions.has(singleton))throw new Error('SCHEMA_INVALID');extensions.add(singleton);
    while(index<parts.length&&parts[index]!.length>1)index++;
  }
}
