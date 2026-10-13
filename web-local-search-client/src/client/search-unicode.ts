import { searchUnicodeData } from './search-unicode-data.ts';
interface UnicodeData {decomposition:Readonly<Record<string,readonly number[]>>;combining:Readonly<Record<string,number>>;composition:Readonly<Record<string,number>>;lower:Readonly<Record<string,readonly number[]>>;cased:readonly (readonly number[])[];case_ignorable:readonly (readonly number[])[];}
const data:UnicodeData=searchUnicodeData;
const MAX_POINTS=262144;
function inRanges(point:number,ranges:readonly (readonly number[])[]):boolean {
  let lo=0,hi=ranges.length-1;
  while(lo<=hi){const mid=(lo+hi)>>>1;const range=ranges[mid]!;if(point<range[0]!)hi=mid-1;else if(point>range[1]!)lo=mid+1;else return true;}
  return false;
}
function expand(point:number,out:number[]):void {
  if(point>=0xac00&&point<0xd7a4){const syllable=point-0xac00;out.push(0x1100+Math.floor(syllable/588),0x1161+Math.floor((syllable%588)/28));if(syllable%28!==0)out.push(0x11a7+syllable%28);}
  else {const parts=data.decomposition[point];if(parts===undefined)out.push(point);else for(const part of parts)expand(part,out);}
  if(out.length>MAX_POINTS)throw new Error('SEARCH_WORK_LIMIT');
}
function compose(a:number,b:number):number|undefined {
  if(a>=0x1100&&a<0x1113&&b>=0x1161&&b<0x1176)return 0xac00+(a-0x1100)*588+(b-0x1161)*28;
  if(a>=0xac00&&a<0xd7a4&&(a-0xac00)%28===0&&b>0x11a7&&b<0x11c3)return a+b-0x11a7;
  return data.composition[a+':'+b];
}
function text(points:readonly number[]):string {return points.map(point=>String.fromCodePoint(point)).join('');}
/** Frozen Unicode 17 NFKC. Does not change signed bytes or depend on host ICU. */
export function nfkcV1(value:string):string {
  if(value.length>65536)throw new Error('SEARCH_WORK_LIMIT');
  const decomposed:number[]=[];for(const character of value)expand(character.codePointAt(0)!,decomposed);
  const ordered:number[]=[];let segment:number[]=[];
  const flush=()=>{segment.sort((a,b)=>(data.combining[a]??0)-(data.combining[b]??0));for(const point of segment)ordered.push(point);segment=[];};
  for(const point of decomposed){if((data.combining[point]??0)===0){flush();ordered.push(point);}else segment.push(point);}flush();
  const result:number[]=[];let starterPosition=-1,lastClass=0;
  for(const point of ordered){const cls=data.combining[point]??0;const merged=starterPosition>=0&&(lastClass===0||lastClass<cls)?compose(result[starterPosition]!,point):undefined;
    if(merged!==undefined)result[starterPosition]=merged;
    else {if(cls===0)starterPosition=result.length;result.push(point);lastClass=cls;}
  }
  return text(result);
}
/** Default full lowercase; the language-neutral Final_Sigma rule is contextual. */
export function searchTextV1(value:string):string {
  const points=Array.from(nfkcV1(value),character=>character.codePointAt(0)!);const out:number[]=[];
  for(let index=0;index<points.length;++index){const point=points[index]!;
    if(point===0x03a3){let before=index-1,after=index+1;while(before>=0&&inRanges(points[before]!,data.case_ignorable))--before;while(after<points.length&&inRanges(points[after]!,data.case_ignorable))++after;
      out.push(before>=0&&inRanges(points[before]!,data.cased)&&(after>=points.length||!inRanges(points[after]!,data.cased))?0x03c2:0x03c3);
    }else out.push(...(data.lower[point]??[point]));
  }
  return text(out);
}
