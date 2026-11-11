(() => {
 const sidebar=document.querySelector('.schedule-sidebar');if(!sidebar)return;
 function update(){
  if(!location.pathname.endsWith('/schedule_management.php'))return;
  const panel=new URLSearchParams(location.search).get('panel');
  const key=panel==='reports'?'reports':panel==='employees'?'employees':location.hash==='#scheduleWorkflow'?'approval':location.hash==='#scheduleEmployeeSetup'?'groups':panel==='hours'?'hours':panel==='dashboard'?'dashboard':'monthly';
  sidebar.querySelectorAll('a').forEach(a=>{a.classList.remove('active');a.removeAttribute('aria-current');});
  const active=sidebar.querySelector(`[data-schedule-nav="${key}"]`);if(active){active.classList.add('active');active.setAttribute('aria-current','page');}
 }
 update();addEventListener('hashchange',update);addEventListener('popstate',update);
})();
