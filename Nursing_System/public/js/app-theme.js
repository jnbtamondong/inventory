/* One preference for every workspace. Loaded in the head before screen styles. */
(() => {
  'use strict';
  if (window.NursingTheme) return;
  const key='jvgh.theme', legacyKey='jvgh.cssr.theme', root=document.documentElement;
  let theme='light';
  const read=()=>{try{return (localStorage.getItem(key) ?? localStorage.getItem(legacyKey))==='dark'?'dark':'light';}catch{return theme;}};
  function syncButtons(){
    document.querySelectorAll('[data-nursing-theme-toggle]').forEach(button=>{
      button.setAttribute('aria-checked',String(theme==='dark'));
      const state=button.querySelector('[data-theme-state]');if(state)state.textContent=theme==='dark'?'On':'Off';
    });
  }
  function apply(value,persist){
    theme=value==='dark'?'dark':'light';
    root.dataset.theme=theme;root.dataset.cuTheme=theme;
    if(persist)try{localStorage.setItem(key,theme);localStorage.setItem(legacyKey,theme);}catch{}
    syncButtons();
    window.dispatchEvent(new CustomEvent('nursing:themechange',{detail:{theme}}));
    // Existing CSSR charts keep their theme-change contract.
    window.dispatchEvent(new CustomEvent('cssr:themechange',{detail:{theme}}));
  }
  window.NursingTheme=Object.freeze({get:()=>theme,set:value=>apply(value,true)});
  apply(read(),false);
  document.addEventListener('click',event=>{if(event.target.closest('[data-nursing-theme-toggle]'))apply(theme==='dark'?'light':'dark',true);});
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',syncButtons);else syncButtons();
  window.addEventListener('pageshow',()=>apply(read(),false));
  window.addEventListener('storage',event=>{if(event.key===key||event.key===legacyKey||event.key===null)apply(read(),false);});
})();
