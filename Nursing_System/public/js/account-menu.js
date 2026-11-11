/* Shared account presentation. Existing account and logout destinations are preserved. */
(() => {
  function initialize() {
    if(document.body.classList.contains('proc-app')&&document.body.dataset.procLayoutReady!=='true'){
      document.addEventListener('proc-layout-ready',initialize,{once:true});return;
    }
    if (document.querySelector('.ns-account-menu')) return;
    const existing = document.querySelector('.cu-profile');
    if (existing) { existing.classList.add('ns-account-menu'); return; }
    if (!document.body.matches('.module-ui')) return;
    const header = document.querySelector('.navbar,.proc-top,.ed-head,.ed-expiring-main .page-head,.standalone-user-header,.backup-header') || document.querySelector('.user-info')?.closest('header,nav');
    if (!header) return;
    let identity = {};
    try { identity = JSON.parse(document.querySelector('#ns-account-identity')?.textContent || '{}'); } catch (_) { /* Existing labels remain usable. */ }
    const old = header.querySelector('.proc-user,.user-info');
    const oldName = old?.querySelector('strong')?.textContent || old?.textContent.replace(/^Welcome,\s*/i,'').replace(/\s*\([^)]*\)\s*$/,'').split('·')[0].trim();
    const name = identity.name || oldName || 'Account';
    const role = identity.role || old?.querySelector('small')?.textContent || '';
    const logout = header.querySelector('a[href*="logout"]');
    const settings = header.querySelector('a[href*="account_settings"]');
    const destinations = [settings?.getAttribute('href') || '/Nursing_System/pages/account_settings.php', '/Nursing_System/pages/system_menu.php', logout?.getAttribute('href') || (document.body.classList.contains('proc-app') ? '/Nursing_System/CSSR_INVENTORY/logout.php' : '/Nursing_System/api/logout.php')];
    const menu = document.createElement('details'); menu.className = 'ns-account-menu';
    const summary = document.createElement('summary'); summary.setAttribute('aria-label','Account menu');
    const avatar = document.createElement('span'); avatar.className='ns-account-avatar';
    avatar.textContent=name.trim().split(/\s+/u).slice(0,2).map(part=>Array.from(part)[0]).join('').toUpperCase();
    const copy=document.createElement('span');copy.className='ns-account-copy';
    const strong=document.createElement('strong');strong.textContent=name;
    const small=document.createElement('small');small.textContent=role;copy.append(strong,small);
    const arrow=document.createElement('span');arrow.className='ns-account-chevron';arrow.setAttribute('aria-hidden','true');arrow.textContent='›';
    summary.append(avatar,copy,arrow);menu.append(summary);
    const panel=document.createElement('div');panel.className='ns-account-panel';
    const heading=document.createElement('strong');heading.textContent=name;
    const subtitle=document.createElement('p');subtitle.textContent=role;panel.append(heading,subtitle);
    ['Account settings','Switch workspace','Sign out'].forEach((label,index)=>{const link=document.createElement('a');link.href=destinations[index];link.textContent=label;panel.append(link);});
    menu.append(panel);
    if(old)old.replaceWith(menu);else (header.querySelector('.navbar-menu,.proc-top-actions,.head-actions') || header).append(menu);
    header.querySelectorAll('.proc-user').forEach(user=>user.remove());
    // Keep notification controls and other header actions in place.
    header.querySelectorAll('a[href*="logout"],a[href*="account_settings"],a[href*="system_menu"]').forEach(link=>{if(!menu.contains(link))link.remove();});
    document.addEventListener('click',event=>{if(!menu.contains(event.target))menu.open=false;});
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&menu.open){menu.open=false;summary.focus();}});
  }
  // Wait for each module's own header controller to finish building its controls.
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(initialize,0),{once:true});
  else setTimeout(initialize,0);
})();
