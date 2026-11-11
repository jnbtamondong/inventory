/* Presentation only: never intercept submits, delay navigation, or change inventory data. */
(() => {
  'use strict';
  const reduced=matchMedia('(prefers-reduced-motion: reduce)');
  const pending=new Map(),active=new Set(),seen=new WeakSet();
  const canAnimate=()=>!reduced.matches&&!document.hidden;
  function animate(node,keyframes,duration){
    if(!canAnimate()||!node.animate)return null;
    const animation=node.animate(keyframes,{duration,easing:'ease-out'});
    active.add(animation);animation.finished.catch(()=>{}).finally(()=>active.delete(animation));return animation;
  }
  function busy(target,value,label='Processing request…'){
    if(!target)return;
    const existing=pending.get(target);
    if(!value){
      if(!existing)return;
      clearTimeout(existing.timer);existing.panel?.remove();
      if(existing.aria===null)target.removeAttribute('aria-busy');else target.setAttribute('aria-busy',existing.aria);
      pending.delete(target);return;
    }
    if(existing)return;
    const state={aria:target.getAttribute('aria-busy'),panel:null,timer:null};pending.set(target,state);target.setAttribute('aria-busy','true');
    // Slow-request feedback only; fast requests never flash a placeholder.
    state.timer=setTimeout(()=>{
      if(!target.isConnected||pending.get(target)!==state)return;
      const panel=document.createElement('div');panel.className='cx-loading';panel.setAttribute('role','status');
      if(label){const text=document.createElement('span');text.className='cx-loading-label';text.textContent=label;panel.append(text);}
      for(let i=0;i<2;i++){const line=document.createElement('div');line.className='cx-skeleton-line';line.setAttribute('aria-hidden','true');panel.append(line);}
      const container=target.querySelector('.cu-card-body')||target;container.prepend(panel);state.panel=panel;
    },180);
  }
  function dismiss(node){
    if(!node)return;
    const animation=animate(node,[{opacity:1},{opacity:0}],100);
    if(animation)animation.finished.catch(()=>{}).finally(()=>node.remove());else node.remove();
  }
  const counterCleanups=new Set();
  function count(node){
    const text=node.textContent.trim();
    if(!canAnimate()||!/^[\d,]+$/.test(text))return;
    const total=Number(text.replaceAll(',',''));if(!Number.isSafeInteger(total)||total<2)return;
    // Keep the exact server-rendered figure available to assistive technology and copying.
    // Only an aria-hidden overlay counts, and only on aggregate KPI cards.
    const computed=getComputedStyle(node),originalOpacity=node.style.opacity;
    const shell=document.createElement('span');shell.className='cx-counter-shell';
    const visual=document.createElement('span');visual.className='cx-counter-overlay';visual.setAttribute('aria-hidden','true');
    for(const key of ['font','color','letterSpacing','fontVariantNumeric','marginTop','paddingTop','textAlign'])visual.style[key]=computed[key];
    node.before(shell);shell.append(node,visual);node.style.opacity='0';
    let frame=0,finished=false;
    const finish=()=>{if(finished)return;finished=true;cancelAnimationFrame(frame);node.style.opacity=originalOpacity;shell.replaceWith(node);counterCleanups.delete(finish);};
    counterCleanups.add(finish);const start=performance.now(),from=Math.floor(total*.94),format=new Intl.NumberFormat('en-PH');
    function tick(now){if(!canAnimate()||!node.isConnected){finish();return;}const progress=Math.min(1,(now-start)/280);visual.textContent=format.format(Math.round(from+(total-from)*(1-Math.pow(1-progress,3))));if(progress<1)frame=requestAnimationFrame(tick);else finish();}
    tick(start);
  }
  const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{
    if(!entry.isIntersecting)return;observer.unobserve(entry.target);
    if(seen.has(entry.target))return;seen.add(entry.target);
    if(entry.target.matches('.cu-stat-value,.summary-card>strong'))count(entry.target);
    else animate(entry.target,[{opacity:.45},{opacity:1}],220);
  }),{threshold:.12});
  document.querySelectorAll('.cu-stats .cu-stat-value,.cu-dashboard-kpis .cu-stat-value,.summary-card>strong,#cu-issuance-chart,#cu-stock-chart,.rp-chart,.main-content canvas').forEach(node=>observer.observe(node));
  function finish(){counterCleanups.forEach(done=>done());active.forEach(animation=>animation.finish());}
  reduced.addEventListener('change',()=>{if(reduced.matches)finish();});
  document.addEventListener('visibilitychange',()=>{if(document.hidden)finish();});
  window.addEventListener('beforeprint',finish);
  window.addEventListener('pagehide',()=>{finish();[...pending.keys()].forEach(node=>busy(node,false));});
  // Restore immediately on back/forward cache; no replay or stuck loading state.
  window.addEventListener('pageshow',event=>{if(event.persisted){finish();[...pending.keys()].forEach(node=>busy(node,false));}});
  const legacyOverlay=document.querySelector('#loadingOverlay');
  if(legacyOverlay){const update=()=>busy(legacyOverlay.querySelector('.loading-card'),legacyOverlay.classList.contains('open'),'');new MutationObserver(update).observe(legacyOverlay,{attributes:true,attributeFilter:['class']});update();}
  window.cssrMotion=Object.freeze({busy,dismiss});
})();
