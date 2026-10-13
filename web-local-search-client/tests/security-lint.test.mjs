import { test } from 'node:test';
import assert from 'node:assert/strict';
import { violations, checkProject } from '../tools/security-lint.mjs';

test('dangerous sink mutations are rejected by syntax policy',()=>{
  for(const input of ['eval(input)','\\u0065val(input)','new Function(input)','node.innerHTML=input','node["outerHTML"]=input','node.insertAdjacentHTML("beforeend",input)','setTimeout("evil()",1)','setInterval(`evil()`,1)'])assert.ok(violations(input).length>0,input);
  assert.deepEqual(violations('// eval(input)\nnode.textContent="innerHTML";setTimeout(()=>run(),1)'),[]);
  assert.deepEqual(checkProject(),[]);
});
