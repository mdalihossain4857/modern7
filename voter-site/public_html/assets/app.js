/* ভোটার অনুসন্ধান — সার্ভার (PHP + MySQL) সংস্করণ */
const $=i=>document.getElementById(i),BN='০১২৩৪৫৬৭৮৯',bn=n=>String(n).replace(/\d/g,d=>BN[d]);
const esc=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const pct=(a,b)=>b?bn((a*100/b).toFixed(1))+'%':'—';
let SEC=[],AREA={},ROWS=[],pg=0,PS=50,N=0,HRE=null,anaDirty=true,ksEls=null,reqId=0;
const FIELDS=['q','sc','w','a','g','p','a1','a2','so','c1','c2','c3'];

function hl(s){s=esc(s);return HRE?s.replace(HRE,'<mark>$1</mark>'):s}
function params(extra){const u=new URLSearchParams();FIELDS.forEach(i=>{const e=$(i);const v=e.type=='checkbox'?(e.checked?'1':''):e.value.trim();if(v)u.set(i,v)});for(const k in extra)u.set(k,extra[k]);return u}
async function api(act,extra){
 const r=await fetch('api.php?'+params(Object.assign({act},extra||{})),{credentials:'same-origin',headers:{'Accept':'application/json'}});
 if(r.status==401){location.href='login.php';throw new Error('লগইন প্রয়োজন')}
 if(!r.ok)throw new Error('সার্ভার ত্রুটি ('+r.status+')');
 return r.json();
}

async function init(){
 const M=await api('meta');SEC=M.sections;SEC.forEach(s=>AREA[s.area_no]=s.area_name);
 const ws=[...new Set(SEC.map(s=>s.ward))].sort();
 $('w').innerHTML='<option value="">সব ওয়ার্ড</option>'+ws.map(w=>`<option value="${esc(w)}">ওয়ার্ড ${esc(w)}</option>`).join('');
 $('p').innerHTML='<option value="">সব পেশা</option>'+M.prof.map(([p,c])=>`<option value="${esc(p)}">${esc(p||'(ফাঁকা)')} (${bn(c)})</option>`).join('');
 areas();$('tot').textContent='মোট ভোটার: '+bn(Number(M.total).toLocaleString('en'));
 $('ld').classList.add('hide');$('app').classList.remove('hide');
 if(!M.total){$('tb').innerHTML='<tr><td colspan="9" style="text-align:center;padding:30px">ডাটাবেসে এখনো কোনো ভোটার নেই। অ্যাডমিন প্যানেল থেকে ডেটা আমদানি করুন।</td></tr>';return}
 run();
}
function areas(){const w=$('w').value,m=new Map();SEC.forEach(s=>{if(!w||s.ward==w)m.set(s.area_no,s.area_name)});$('a').innerHTML='<option value="">সব এলাকা</option>'+[...m].map(([k,v])=>`<option value="${esc(k)}">${esc(v)} [${esc(k)}]</option>`).join('')}

function run(){
 const raw=$('q').value.trim().split(/\s+/).filter(Boolean),T=[...new Set([...raw,...raw.map(x=>x.replace(/\d/g,d=>BN[d]))])];
 HRE=T.length?new RegExp('('+T.map(x=>esc(x).replace(/[.*+?^${}()|[\]\\]/g,'\\$&')).join('|')+')','g'):null;
 pg=0;load();ana();
}
const KL=['ফলাফল','মহিলা','পুরুষ','গড় বয়স'];
async function load(){
 const id=++reqId;$('tb').classList.add('busy');
 let d;try{d=await api('search',{pg})}catch(e){if(id==reqId){$('tb').innerHTML=`<tr><td colspan="9" style="text-align:center;padding:30px">${esc(e.message)}</td></tr>`;$('tb').classList.remove('busy')}return}
 if(id!=reqId)return;
 $('tb').classList.remove('busy');ROWS=d.rows;N=d.n;PS=d.ps;
 const V=[bn(N),bn(d.f)+' ('+pct(d.f,N)+')',bn(d.m)+' ('+pct(d.m,N)+')',bn(d.av.toFixed(1))];
 if(!ksEls){$('ks').innerHTML=KL.map(l=>`<div class="k"><b></b><span>${l}</span></div>`).join('');ksEls=[...$('ks').querySelectorAll('b')]}
 V.forEach((v,i)=>{if(ksEls[i].textContent!==v)ksEls[i].textContent=v});
 $('tb').innerHTML=ROWS.map((r,k)=>`<tr class="r" data-k="${k}"><td>${bn(r.sl)}</td><td><span class="d" style="background:${r.g=='পুরুষ'?'var(--m)':'var(--f)'}"></span><b>${hl(r.name)}</b></td><td>${hl(r.vno)}</td><td>${hl(r.father)}</td><td>${hl(r.mother)}</td><td>${hl(r.dob)} <span class="mu">(${r.age==null?'—':bn(r.age)})</span></td><td>${hl(r.prof)}</td><td>ওয়ার্ড ${esc(r.w)}<br><span class="mu">${esc(r.area)}</span></td><td>${hl(r.addr)}</td></tr>`).join('')||'<tr><td colspan="9" style="text-align:center;padding:30px">কোনো ফলাফল পাওয়া যায়নি</td></tr>';
 const tp=Math.max(1,Math.ceil(N/PS));$('pi').textContent=`পৃষ্ঠা ${bn(pg+1)} / ${bn(tp)}`;$('pv').disabled=pg==0;$('nx').disabled=pg>=tp-1;
}
$('tb').addEventListener('click',e=>{const tr=e.target.closest('tr.r');if(tr)show(ROWS[+tr.dataset.k])});
function show(r){window.CV=r;$('dgc').innerHTML=`<h3>${esc(r.name)}</h3><dl class="dl"><dt>ভোটার নং</dt><dd>${esc(r.vno)}</dd><dt>পিতা</dt><dd>${esc(r.father)}</dd><dt>মাতা</dt><dd>${esc(r.mother)}</dd><dt>জন্ম তারিখ</dt><dd>${esc(r.dob)} (বয়স ${r.age==null?'—':bn(r.age)})</dd><dt>পেশা</dt><dd>${esc(r.prof)||'—'}</dd><dt>লিঙ্গ</dt><dd>${esc(r.g)}</dd><dt>ওয়ার্ড</dt><dd>${esc(r.w)}</dd><dt>ভোটার এলাকা</dt><dd>${esc(r.area)} [${esc(r.an)}]</dd><dt>ঠিকানা</dt><dd>${esc(r.addr)}</dd><dt>সূত্র</dt><dd>PDF পৃষ্ঠা ${bn(r.page)}, ক্রমিক ${bn(r.sl)}</dd></dl>`;$('dg').showModal()}

const bar=(rows,c)=>{const mx=Math.max(1,...rows.map(r=>+r[1]));return rows.map(r=>`<div class="br"><span class="bl" title="${esc(r[0])}">${esc(r[0])}</span><div class="bt"><i style="width:${r[1]/mx*100}%;background:${c||'var(--pri)'}"></i></div><b>${bn(r[1])}</b></div>`).join('')};
function ana(){anaDirty=true;if(!$('v2').classList.contains('hide'))doAna()}
async function doAna(){
 if(!anaDirty)return;anaDirty=false;const id=reqId;
 let d;try{d=await api('ana')}catch(e){$('ch').innerHTML=`<div class="card">${esc(e.message)}</div>`;anaDirty=true;return}
 if(id!=reqId)return;
 const k=d.k,n=+k.n;
 $('k2').innerHTML=[['মোট ভোটার',bn(n)],['মহিলা',pct(+k.f,n)],['গড় বয়স',n?bn((+k.av).toFixed(1)):'—'],['মধ্য বয়স',d.med!=null?bn(d.med):'—'],['তরুণ (১৮–২৯)',pct(+k.yg,n)],['প্রবীণ (৬০+)',pct(+k.sr,n)],['৯০+ বছর',bn(+k.n90)],['০১/০১ জন্ম',pct(+k.d11,n)],['ডুপ্লিকেট সন্দেহ',bn(+k.dup)]].map(([a,b])=>`<div class="k"><b>${b}</b><span>${a}</span></div>`).join('');
 const ws=d.ward.sort((a,b)=>a[0]<b[0]?-1:1),mx=Math.max(1,...ws.map(o=>+o[1]));
 const wh=ws.map(o=>`<div class="br"><span class="bl">ওয়ার্ড ${esc(o[0])}</span><div class="bt"><i style="width:${o[2]/mx*100}%;background:var(--f)"></i><i style="width:${o[3]/mx*100}%;background:var(--m)"></i></div><b>${bn(o[1])}</b></div>`).join('');
 const BD=[[18,25],[26,35],[36,45],[46,60],[61,75],[76,150]],B=BD.map(([a,b],j)=>[bn(a)+(b>100?'+':'–'+bn(b))+' বছর',+k['b'+(j+1)]]);
 const c=(t,h,x)=>`<div class="card"><h3>${t}</h3>${h}${x?`<div class="mu" style="margin-top:6px">${x}</div>`:''}</div>`;
 $('ch').innerHTML=c('ওয়ার্ড অনুযায়ী ভোটার',wh,'<span class="lg"><i style="background:var(--f)"></i>মহিলা<i style="background:var(--m)"></i>পুরুষ</span>')
  +c('বয়সভিত্তিক বিন্যাস (আজকের তারিখ অনুযায়ী)',bar(B))
  +c('জন্ম-দশক অনুযায়ী',bar(d.dec.map(([x,v])=>[bn(x)+' এর দশক',v]),'var(--ok)'))
  +c('শীর্ষ ১০ পেশা',bar(d.prof,'var(--warn)'))+c('সর্বাধিক প্রচলিত প্রথম নাম',bar(d.fn))+c('সর্বাধিক প্রচলিত শেষ নাম/পদবি',bar(d.ln))
  +c('সর্বাধিক ভোটারের বাড়ি/ঠিকানা (প্রথম অংশ)',bar(d.hh,'var(--m)'))
  +c('ভোটার এলাকা অনুযায়ী',bar(d.area.map(([a,v])=>[(AREA[a]||'')+' ['+a+']',v]),'var(--f)'));
}

FIELDS.forEach(i=>$(i).addEventListener(i=='q'?'input':'change',()=>{if(i=='w')areas();clearTimeout(window._t);window._t=setTimeout(run,i=='q'?300:0)}));
$('pv').onclick=()=>{pg--;load()};$('nx').onclick=()=>{pg++;load()};$('pr').onclick=()=>{pvEnd();print()};
$('rs').onclick=()=>{['q','a1','a2'].forEach(i=>$(i).value='');['sc','w','g','p','so'].forEach(i=>$(i).selectedIndex=0);['c1','c2','c3'].forEach(i=>$(i).checked=false);areas();run()};
$('t1').onclick=()=>{$('v1').classList.remove('hide');$('v2').classList.add('hide');$('t1').className='on';$('t2').className=''};
$('t2').onclick=()=>{$('v2').classList.remove('hide');$('v1').classList.add('hide');$('t2').className='on';$('t1').className='';doAna()};
$('ex').onclick=()=>{location.href='api.php?'+params({act:'csv'})};
$('dgClose').onclick=()=>$('dg').close();
$('dgPrint').onclick=()=>pvPrint(window.CV);
$('dg').addEventListener('close',()=>pvEnd());
$('lo').addEventListener('submit',e=>{if(!confirm('লগ আউট করবেন?'))e.preventDefault()});
(function(){var h=document.documentElement,b=$('themeBtn'),k='mc-theme',t='light';try{t=localStorage.getItem(k)==='dark'?'dark':'light'}catch(e){}function a(){h.setAttribute('data-theme',t);b.textContent=t==='dark'?'☀️':'🌙'}a();b.onclick=function(){t=t==='dark'?'light':'dark';try{localStorage.setItem(k,t)}catch(e){}a()}})();
init().catch(e=>{$('ld').textContent='ডেটা লোড করা যায়নি: '+e.message});
