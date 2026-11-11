/* Read before first paint. A browser preference survives refresh, logout and login. */
(() => {
  'use strict';
  const key='jvgh.cssr.theme',root=document.documentElement;
  let theme='light',timer;
  try{if(localStorage.getItem(key)==='dark')theme='dark';}catch{}
  root.dataset.cuTheme=theme;
  function sync(){document.querySelectorAll('[data-cu-theme-toggle]').forEach(button=>{
    const dark=theme==='dark',label=dark?'Light Mode':'Dark Mode';
    button.removeAttribute('aria-pressed');button.setAttribute('aria-label','Switch to '+label);button.title='Switch to '+label;
    button.querySelector('[data-cu-theme-icon]').textContent=dark?'☀️':'🌙';button.querySelector('[data-cu-theme-label]').textContent=label;
  });}
  function apply(value,persist=true){
    theme=value==='dark'?'dark':'light';
    if(root.dataset.cuTheme===theme){sync();return;}
    clearTimeout(timer);root.classList.remove('cu-theme-changing');
    if(!matchMedia('(prefers-reduced-motion: reduce)').matches){root.classList.add('cu-theme-changing');clearTimeout(timer);timer=setTimeout(()=>root.classList.remove('cu-theme-changing'),220);}
    root.dataset.cuTheme=theme;
    if(persist)try{localStorage.setItem(key,theme);}catch{}
    sync();window.dispatchEvent(new CustomEvent('cssr:themechange',{detail:{theme}}));
  }
  document.addEventListener('DOMContentLoaded',()=>{sync();document.querySelectorAll('[data-cu-theme-toggle]').forEach(button=>button.addEventListener('click',()=>apply(theme==='dark'?'light':'dark')));});
  window.addEventListener('storage',event=>{if(event.key===key||event.key===null)apply(event.newValue,false);});
  window.addEventListener('pageshow',()=>{try{apply(localStorage.getItem(key),false);}catch{sync();}});
})();
