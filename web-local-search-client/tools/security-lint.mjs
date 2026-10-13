/** Compiler-token sink checks. These are scoped controls, not a security audit. */
import { createScanner, SyntaxKind } from 'typescript/unstable/ast';
import { readFileSync, readdirSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const forbiddenProperties=new Set(['innerHTML','outerHTML','insertAdjacentHTML','write','writeln']);
const forbiddenCalls=new Set(['eval','Function']);
export function violations(source,name='input.ts') {
  const scanner=createScanner(true,undefined,source);
  const tokens=[];
  for(let kind=scanner.scan();kind!==SyntaxKind.EndOfFile;kind=scanner.scan())tokens.push({kind,text:scanner.getTokenText(),value:scanner.getTokenValue()});
  const found=[];
  for(let i=0;i<tokens.length;i++) {
    const token=tokens[i], previous=tokens[i-1], next=tokens[i+1];
    if(token.kind===SyntaxKind.Identifier&&forbiddenCalls.has(token.value)&&next?.text==='(')found.push('DYNAMIC_CODE');
    if(token.kind===SyntaxKind.Identifier&&forbiddenProperties.has(token.value)&&previous?.text==='.')found.push('UNSAFE_DOM_SINK');
    if(token.kind===SyntaxKind.StringLiteral&&forbiddenProperties.has(token.value)&&previous?.text==='['&&next?.text===']')found.push('UNSAFE_DOM_SINK');
    if(token.kind===SyntaxKind.Identifier&&['setTimeout','setInterval'].includes(token.value)&&next?.text==='('&&[SyntaxKind.StringLiteral,SyntaxKind.NoSubstitutionTemplateLiteral].includes(tokens[i+2]?.kind))found.push('STRING_TIMER');
  }
  return found;
}
const root=fileURLToPath(new URL('../',import.meta.url));
function files(dir) { return readdirSync(dir,{withFileTypes:true}).flatMap(entry=>entry.isDirectory()?files(join(dir,entry.name)):entry.name.endsWith('.ts')?[join(dir,entry.name)]:[]); }
export function checkProject() {
  const problems=[];
  for(const file of files(join(root,'src')))for(const code of violations(readFileSync(file,'utf8'),file))problems.push(relative(root,file)+': '+code);
  return problems;
}
if(process.argv[1]===fileURLToPath(import.meta.url)) {
  const problems=checkProject();for(const problem of problems)console.error(problem);
  console.log(JSON.stringify({scope:'TypeScript source compiler-token sink policy',status:problems.length?'FAIL':'PASS',finding_count:problems.length}));
  process.exitCode=problems.length?1:0;
}
