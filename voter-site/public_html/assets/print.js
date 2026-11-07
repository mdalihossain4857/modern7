/* ===== ভোটার তথ্য: প্রিন্ট / পিডিএফ ===== */
const PV_SHOP=APP.shop,PV_ADDR=APP.addr;
function pvEnd(){document.documentElement.classList.remove('pv');const s=document.getElementById('pvPage');if(s)s.remove();const p=$('prt');if(p)p.innerHTML='';if(window.PVT!=null){document.title=window.PVT;window.PVT=null}}
function pvHTML(r){
 const e=x=>esc(String(x==null?'':x)),z=n=>String(n).padStart(2,'0'),d=new Date(),h=d.getHours(),
  per=h<4?'রাত':h<12?'সকাল':h<15?'দুপুর':h<18?'বিকাল':h<20?'সন্ধ্যা':'রাত',
  dt=bn(z(d.getDate()))+'/'+bn(z(d.getMonth()+1))+'/'+bn(d.getFullYear()),
  tm=per+' '+bn(z(h%12||12))+':'+bn(z(d.getMinutes())),
  sm=document.querySelector('header .w small'),src=sm?sm.textContent.trim():'',
  row=(l,v)=>`<div class="pv-r"><span>${l}</span><b>${v}</b></div>`,
  tri='<svg class="pv-tri" viewBox="0 0 300 4" preserveAspectRatio="none" aria-hidden="true"><rect width="100" height="4" fill="#d92d3f"/><rect x="100" width="100" height="4" fill="#f6b81a"/><rect x="200" width="100" height="4" fill="#0b7a4b"/></svg>',
  logo='<svg class="pv-logo" viewBox="0 0 72 72" fill="none" aria-hidden="true"><defs><linearGradient id="pvRing" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff1744"/><stop offset=".2" stop-color="#ffea00"/><stop offset=".4" stop-color="#00e676"/><stop offset=".6" stop-color="#00e5ff"/><stop offset=".8" stop-color="#2979ff"/><stop offset="1" stop-color="#d500f9"/></linearGradient><radialGradient id="pvBg" cx=".3" cy=".25" r=".9"><stop offset="0" stop-color="#1d3c70"/><stop offset="1" stop-color="#0a1730"/></radialGradient></defs><circle cx="36" cy="36" r="34" fill="url(#pvRing)"/><circle cx="36" cy="36" r="31" fill="url(#pvBg)"/><g transform="translate(16.8 16.8) scale(.8)"><rect x="5" y="7" width="38" height="25" rx="4" stroke="#fff" stroke-width="3"/><path d="M17 40h14M24 32v8" stroke="#fff" stroke-width="3" stroke-linecap="round"/><path d="M15 25V14.5l4.5 5.5L24 14.5 28.5 20 33 14.5V25" stroke="#7fc4ff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/></g></svg>';
 return `<div class="pv-top">${logo}<div class="pv-id"><div class="pv-sn">${PV_SHOP}</div><div class="pv-sa">${PV_ADDR}</div></div><div class="pv-ti"><b>ভোটার তথ্য</b><span>তারিখ: ${dt}</span></div></div>${tri}`
  +(src?`<div class="pv-src">${e(src)}</div>`:'<div class="pv-src"></div>')
  +`<div class="pv-nm"><i style="color:${r.g=='পুরুষ'?'#0b7a4b':'#d92d3f'}">●</i>${e(r.name)}</div>`
  +`<div class="pv-g">`
  +row('ভোটার নং',e(r.vno))+row('পিতা',e(r.father)||'—')+row('মাতা',e(r.mother)||'—')
  +row('জন্ম তারিখ',e(r.dob)+' (বয়স '+bn(r.age)+')')+row('পেশা',e(r.prof)||'—')+row('লিঙ্গ',e(r.g))
  +row('ওয়ার্ড',e(r.w))+row('ভোটার এলাকা',e(r.area)+' ['+e(r.an)+']')+row('ঠিকানা',e(r.addr)||'—')
  +row('সূত্র','PDF পৃষ্ঠা '+bn(r.page)+', ক্রমিক '+bn(r.sl))
  +`</div><div class="pv-ft">${tri}<div class="pv-fi"><span>মুদ্রণের সময়: ${dt}, ${tm}</span><b>${PV_SHOP}, ${PV_ADDR}</b></div></div>`;
}
function pvPrint(i){ /* i = ভোটারের রেকর্ড */
 const r=i;if(!r)return;pvEnd();fetch('api.php?act=print&vno='+encodeURIComponent(r.vno)).catch(()=>{});
 $('prt').innerHTML=pvHTML(r);
 const st=document.createElement('style');st.id='pvPage';st.textContent='@page{size:A4;margin:0}';document.head.appendChild(st);
 window.PVT=document.title;document.title='ভোটার তথ্য - '+r.name;
 document.documentElement.classList.add('pv');
 addEventListener('afterprint',pvEnd,{once:true});
 setTimeout(()=>print(),80);
}
