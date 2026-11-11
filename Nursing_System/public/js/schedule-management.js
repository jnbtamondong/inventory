(() => {
    'use strict';
    const boot = JSON.parse(document.getElementById('scheduleBoot').textContent);
    const E = window.NsSchedule, $ = id => document.getElementById(id);
    const currentMonth=boot.currentMonth||new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit'}).format(new Date());
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const clone = value => JSON.parse(JSON.stringify(value));
    const icon = name => '<i class="bi bi-' + name + '" aria-hidden="true"></i>';
    const option = (value, label, selected) => '<option value="' + esc(value) + '"' + (String(value) === String(selected) ? ' selected' : '') + '>' + esc(label) + '</option>';
    const position = employee => employee.position || 'Position not recorded';
    const label = employee => employee.name + ' / ' + position(employee);
    const clock=hours=>String(Math.floor(hours)%24).padStart(2,'0')+':'+String(Math.round((hours%1)*60)).padStart(2,'0');
    const leaveDefinitions={OFF:'Rest Day',REQUEST_OFF:'Approved preferred OFF',PH:'Philippine Holiday',SPL:'Special Privilege Leave',SL:'Sick Leave',VL:'Vacation Leave',WL:'Wellness Leave',FL:'Force Leave',ML:'Maternity Leave',PL:'Paternity Leave',SOLO_PARENT:'Solo Parent Leave',OTHER_LEAVE:'Other Authorized Leave',LEAVE:'Leave',TRAINING:'Training',OFFICIAL_BUSINESS:'Official Business'};
    const shiftDescription=code=>{const d=E.duties[code];return d.duration?clock(d.start)+'â€“'+clock(d.start+d.duration)+(d.start+d.duration>=24?' next day':'')+' / '+d.hours+' credited hours / '+(d.meal||0)+' meal hours':leaveDefinitions[code]||'';};
    const summaryStatus=r=>state.analysis.conflicts.some(c=>c.employee_id===r.id&&['UNASSIGNED','ABSENCE','PATTERN','INACTIVE'].includes(c.type))?'INCOMPLETE':state.analysis.conflicts.some(c=>c.employee_id===r.id&&['REST','DUPLICATE','CONSECUTIVE','OFF','OFF_BLOCK'].includes(c.type))?'REST CONFLICT':state.analysis.conflicts.some(c=>c.type==='UNDERSTAFFED')?'COVERAGE CONFLICT':r.status==='COMPLETE'?'EXACT '+r.target+' HOURS':r.status==='DEFICIT'?'BELOW TARGET':'ABOVE TARGET';
    const dutyLabel=code=>code==='REQUEST_OFF'?'OFF':E.duties[code]?.label||'';
    const badge = code => '<span class="schedule-duty" data-duty="' + esc(code) + '">' + esc(dutyLabel(code) || 'Unassigned') + '</span>';
    const state = {data:null, department:boot.department, month:boot.month, dirty:false, busy:false, step:1, workspace:true, view:'list', selected:'', analysis:null, worker:null};
    const dialog = $('scheduleDialog');
    let cancelDialog = null;
    const editable = () => boot.access.edit && state.data && ['DRAFT','RETURNED FOR REVISION'].includes(state.data.status) && !state.busy;
    const activeStaff = () => state.data?.payload.staff.filter(e => e.active) || [];
    const employeeById = id => state.data?.payload.staff.find(e => String(e.id) === String(id));
    const departmentLabel = () => [...$('scheduleDepartment').options].find(o => o.value === state.department)?.textContent || state.department;
    const monthLabel = () => new Intl.DateTimeFormat('en', {month:'long',year:'numeric',timeZone:'UTC'}).format(new Date(state.month + '-01T00:00:00Z'));
    const notice = (message, tone = '') => { $('scheduleNotice').textContent = message; $('scheduleNotice').dataset.tone = tone; };
    function noQuotas(rules, count) {
        const p=state.data?.payload,required=p?.policy?.requiredShifts||[],fixed=p?.policy?.mode==='FIXED';
        const shifts=!fixed&&(p?.staff[0]?.pattern==='12')&&required.join(',')==='AM,PM,NIGHT8'?['DAY12','NIGHT12']:required;
        return {...rules, reviewed:true, staffingTargets:rules.staffingTargets||{TRIAGE:0,NURSE:0,NA:0}, staffingShifts:rules.staffingShifts||[],
            coverage:Object.fromEntries(E.clinical.map(code => [code,{min:shifts.includes(code)?1:0,max:Math.max(1,count)}]))};
    }
    function normalize(data) {
        data.payload.assignments = Object.fromEntries(Object.entries(data.payload.assignments || {}).map(([id, days]) => [id, {...days}]));
        E.configure(data.payload);
        data.payload.requests ||= [];
        return data;
    }
    function closeDialog() {
        dialog.close();
        if (cancelDialog) { const callback = cancelDialog; cancelDialog = null; callback(false); }
    }
    function openDialog(title, html) {
        closeDialog();
        $('scheduleDialogTitle').textContent = title;
        $('scheduleDialogBody').innerHTML = html;
        dialog.showModal();
        dialog.querySelector('input,select,textarea,button')?.focus();
        dialog.querySelectorAll('[data-cancel]').forEach(button => button.onclick = closeDialog);
    }
    function confirm(title, message) {
        return new Promise(resolve => {
            openDialog(title, '<form class="schedule-form"><p class="full">' + esc(message) + '</p><footer><button type="button" class="btn btn-light" data-cancel>Cancel</button><button type="submit" class="btn btn-primary">Continue</button></footer></form>');
            cancelDialog = resolve;
            dialog.querySelector('form').onsubmit = event => {event.preventDefault(); cancelDialog = null; closeDialog(); resolve(true);};
        });
    }
    function formError(error) {
        const target = dialog.querySelector('[data-error]');
        if (target) {target.hidden = false; target.textContent = error.message;}
        else notice(error.message, 'error');
    }
    $('scheduleDialogClose').onclick = closeDialog;
    dialog.addEventListener('cancel', event => {event.preventDefault(); closeDialog();});
    function setBusy(value) {
        state.busy = value;
        $('scheduleApp').setAttribute('aria-busy', String(value));
        ['scheduleDepartment','scheduleMonth','scheduleYear','scheduleReload','scheduleHistory','schedulePrevious','scheduleNext'].forEach(id => $(id).disabled = value);
        updateControls();
    }
    async function api(params, body = null) {
        const url = '../api/schedule_management.php' + (body ? '' : '?' + new URLSearchParams(params));
        const response = await fetch(url, body ? {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','X-CSRF-Token':boot.csrf}, body:JSON.stringify(body)} : {credentials:'same-origin'});
        if (response.redirected || !response.headers.get('Content-Type')?.includes('application/json')) throw Error('Please sign in again, then reload this schedule.');
        const result = await response.json();
        if (!response.ok || !result.success) throw Error(result.error || 'Unable to load the schedule.');
        return result.data;
    }
    function syncPeriod() {
        $('scheduleDepartment').value = state.department;
        $('scheduleMonth').value = state.month.slice(5);
        $('scheduleYear').value = state.month.slice(0,4);
    }
    async function load(department = state.department, month = state.month) {
        if (month<currentMonth) {syncPeriod(); notice('Only the current and future months can be selected.','error');return;}
        if (state.busy) {syncPeriod(); return;}
        if (state.dirty && !await confirm('Unsaved schedule', 'Discard unsaved changes and open the selected month?')) {syncPeriod(); return;}
        setBusy(true); notice('Loading department staff...');
        try {
            const data = normalize(await api({department,month}));
            state.data = data; state.department = department; state.month = month; state.dirty = false; state.selected = '';
            state.step = 1; state.workspace = true; state.view='list';
            syncPeriod(); syncSetup();
            render();
            notice(data.exists ? 'Saved schedule loaded.' : boot.access.edit ? activeStaff().length + ' department employees loaded automatically.' : 'No approved schedule is available for this month.');
        } catch (error) {syncPeriod(); notice(error.message,'error');}
        finally {setBusy(false);}
    }
    function syncSetup() {
        if (!boot.access.edit) return;
        const pattern = state.data.payload.staff[0]?.pattern || '8';
        document.querySelectorAll('[name=schedulePattern]').forEach(input => input.checked = input.value === pattern);
        $('scheduleRestHours').value = state.data.exists ? state.data.payload.rules.minRest : 12;
        $('scheduleMaxConsecutive').value = state.data.exists ? state.data.payload.rules.maxConsecutive : 6;
        $('scheduleOffDays').value = state.data.exists ? state.data.payload.rules.offPer7 : 1;
        $('scheduleMode').value=state.data.payload.mode==='MANUAL'?'MANUAL':'AUTO';
    }
    function showSetup(step){if(!state.data||state.busy)return;state.step=Math.max(1,Math.min(9,step));renderStep();const targets={1:'scheduleDepartment',2:'scheduleMonth',3:'scheduleEmployeeSetup',4:'scheduleRequestSetup',5:'scheduleShiftType',6:$('scheduleMode').value==='MANUAL'?'scheduleManual':'scheduleGenerate',7:'scheduleDutyList',8:'scheduleHoursPanel',9:'scheduleWorkflow'};if(state.step===8)selectView('hours');if(state.step===7)selectView('list');const target=$(targets[state.step]);if(target?.tagName==='DETAILS')target.open=true;target?.scrollIntoView({block:'center'});}
    function renderStep(){document.querySelectorAll('[data-setup-step]').forEach(button=>{if(Number(button.dataset.setupStep)===state.step)button.setAttribute('aria-current','step');else button.removeAttribute('aria-current');button.disabled=state.busy;});if($('scheduleStepCount'))$('scheduleStepCount').textContent='Step '+state.step+' of 9';if($('scheduleSetupBack'))$('scheduleSetupBack').disabled=state.busy||state.step===1;if($('scheduleSetupNext'))$('scheduleSetupNext').disabled=state.busy||state.step===9;}
    function showWorkspace() {
        if (!state.data) return;
        state.workspace = true;if(state.step<7)state.step=7;
        render();
    }
    function renderSetup() {
        $('scheduleWorkspace').hidden = false;
        if (!boot.access.edit) return;
        $('scheduleSetup').hidden = false;
        renderStep();
        $('scheduleSetupCount').textContent=activeStaff().length+' employees';
        $('scheduleSetupRoster').innerHTML=state.data.payload.staff.map(e=>'<div class="roster-person"><strong>'+esc(e.name)+'</strong><small>'+esc(e.position+' / '+e.department)+(e.active?'':' / Inactive in this department')+'</small>'+(!e.active?'<button type="button" class="btn btn-light" data-remove-inactive="'+e.id+'">Remove inactive employee</button>':'')+'</div>').join('')||'<p>No eligible active nursing employees are assigned to this department.</p>';
        $('scheduleRequestsList').innerHTML='<table class="schedule-table"><thead><tr><th>Employee</th><th>Date</th><th>Availability</th><th>Status</th></tr></thead><tbody>'+state.data.payload.requests.map(r=>'<tr><td>'+esc(employeeById(r.employee_id)?.name)+'</td><td>'+esc(r.date)+'</td><td>'+esc(r.type)+'</td><td>'+esc(r.status)+'</td></tr>').join('')+(state.data.payload.internalLeaveIndicators||[]).map(r=>'<tr><td>'+esc(employeeById(r.employee_id)?.name)+'</td><td>'+esc(r.date)+'</td><td>'+esc(r.leave_type)+'</td><td>Internal clearance / HR filing outside the system</td></tr>').join('')+'</tbody></table>';
        $('scheduleGenerate').querySelector('strong').textContent='Generate Schedule';
        $('scheduleGenerate').hidden=$('scheduleMode').value==='MANUAL';
        $('scheduleManual').hidden=$('scheduleMode').value!=='MANUAL';
        document.querySelector('.pattern-options').hidden=state.data?.payload.policy?.mode==='FIXED';
        $('scheduleFixedPattern').hidden=state.data?.payload.policy?.mode!=='FIXED';
        $('scheduleFixedPattern').textContent='Fixed daytime duty Â· '+(state.department==='OPD'?'6 / 7 / 8 / M':'8 / M');
    }
    async function applySetup() {
        if (!editable()) return false;
        if (!$('scheduleRestHours').reportValidity()) return false;
        const pattern = document.querySelector('[name=schedulePattern]:checked').value;
        const payload = state.data.payload;
        const changedPattern = payload.staff.some(e => e.active && e.pattern !== pattern);
        if (changedPattern && Object.values(payload.assignments).some(days => Object.values(days).some(code => E.duties[code]?.hours)) &&
            !await confirm('Change duty pattern?', 'Existing assignments will remain. Pattern differences will appear in Schedule checks.')) return false;
        payload.staff.forEach(e => {if (e.active) {e.pattern = pattern; e.preference = 'ANY';}});
        payload.rules = noQuotas({...payload.rules, minRest:Number($('scheduleRestHours').value), maxConsecutive:Number($('scheduleMaxConsecutive').value), minOff:1, maxOff:31, offPer7:Number($('scheduleOffDays').value)}, payload.staff.length);
        changed();
        return true;
    }
    function changed() {state.dirty = true; render();}
    function updateControls() {
        const canEdit = editable(), ready = !!state.data && !state.busy;
        renderStep();
        $('scheduleYear').min=currentMonth.slice(0,4);
        for(const option of $('scheduleMonth').options)option.disabled=$('scheduleYear').value+'-'+option.value<currentMonth;
        $('schedulePrevious').disabled=state.busy||state.month<=currentMonth;
        if($('scheduleMode'))$('scheduleMode').disabled=!canEdit;
        if($('scheduleValidate'))$('scheduleValidate').disabled=!ready;
        ['scheduleGenerate','scheduleManual','scheduleReset'].forEach(id => {if ($(id)) $(id).disabled = !canEdit || !activeStaff().length;});
        document.querySelectorAll('[name=schedulePattern],.rest-fields input,.rest-fields select').forEach(input => input.disabled = !canEdit);
        document.querySelectorAll('[data-edit-date],[data-edit-duty]').forEach(button => button.disabled = !canEdit);
        if ($('scheduleDirty')) $('scheduleDirty').hidden = !state.dirty;
        if ($('scheduleSaveState')) $('scheduleSaveState').textContent = state.busy ? 'Processing...' : state.dirty ? 'Draft has unsaved changes' : state.data?.exists ? 'Saved / ' + state.data.status.toLowerCase() : 'New monthly draft';
        if ($('scheduleWorkflow')) {
            const status = state.data?.status;
            let actions = canEdit ? [['save','Save Draft','floppy'],['submit','Finalize and Publish','send']] : [];
            if (status === 'FOR APPROVAL' && boot.access.approve) actions = [['return','Return for revision','arrow-return-left'],['approve','Approve','check2-circle']];
            if (status === 'APPROVED' && boot.access.approve) actions.push(['publish','Finalize and Publish','calendar2-check']);
            if (['APPROVED','PUBLISHED'].includes(status)) actions.push(['revise','Start revision','pencil']);
            $('scheduleWorkflow').innerHTML = actions.map(([action,title,glyph],i) => '<button class="btn ' + (i === 0 ? 'btn-light' : 'btn-primary') + '" data-workflow="' + action + '" ' +
                (state.busy || (action === 'approve' && state.data.submitted_by === boot.access.user_id) ? 'disabled' : '') + '>' + icon(glyph) + ' ' + title + '</button>').join('');
            if (status === 'FOR APPROVAL' && state.data.submitted_by === boot.access.user_id) $('scheduleSaveState').textContent = 'Awaiting another authorized approver';
        }
    }
    function render() {
        if (!state.data) return;
        const p = state.data.payload;
        // Capacity limits are not part of this workflow; retain the existing rest analysis.
        state.analysis = E.analyze({...p,rules:noQuotas(p.rules,p.staff.length)},state.month,state.data.boundaries,!boot.access.edit);
        $('scheduleStatus').textContent=state.data.status==='PUBLISHED'?'Finalized':state.data.status==='DRAFT'?'Draft':state.data.status.replaceAll('_',' ');
        $('scheduleStatus').dataset.status = state.data.status;
        $('scheduleVersion').textContent = state.data.version ? 'Version ' + state.data.version : '';
        document.querySelectorAll('.target-label').forEach(el=>el.textContent='TARGET: '+(p.rules.target||160)+' HOURS');
        $('scheduleRosterCount').textContent = activeStaff().length;
        const totals = state.analysis.summaries;
        $('scheduleRuleTarget').textContent=p.rules.target||160;
        $('schedulePolicyNote').textContent=p.policy?.mode==='FIXED'?'Fixed daytime duties follow configured operating days; target differences are reported without adding unnecessary duties.':'Previous-month history is optional. Actual rest intervals and nurse coverage are checked.';
        $('scheduleMetrics').innerHTML=[[(p.rules.target||160)+'h Target',totals.filter(e=>e.status==='COMPLETE').length+'/'+p.staff.length,'complete'],['Coverage Gaps',state.analysis.conflicts.filter(c=>c.type==='UNDERSTAFFED').length,'deficit'],['PM â†’ AM',pmAmConflicts().length,'excess']].map(([name,count,tone])=>'<div class="schedule-metric '+tone+'"><strong>'+count+'</strong><span>'+name+'</span></div>').join('');
        renderSetup(); renderCalendar(); renderDutyList(); renderSummary(); renderChecks(); updateControls(); selectView(state.view);
    }
    function pmAmConflicts(){
        const p=state.data.payload;if(p.policy?.mode==='FIXED')return [];
        return p.staff.flatMap(employee=>{const own=p.assignments[employee.id]||{},entries=[...Object.entries(own).map(([date,duty])=>({date,duty})),...state.data.boundaries.filter(b=>b.employee_id===employee.id)];return entries.filter(a=>['PM','PM8','NIGHT12'].includes(a.duty)).flatMap(a=>entries.filter(b=>b.date===E.addDays(a.date,1)&&['AM','ADJUST8','DAY12'].includes(b.duty)&&(a.date.startsWith(state.month)||b.date.startsWith(state.month))).map(b=>({employee_id:employee.id,date:b.date})));});
    }
    function selectView(view){state.view=view;$('scheduleCalendar').hidden=view!=='calendar';$('scheduleDutyList').hidden=view!=='list';$('scheduleHoursPanel').hidden=view!=='hours';document.querySelector('.schedule-calendar-section').hidden=view==='hours';for(const [id,value] of [['scheduleListView','list'],['scheduleCalendarView','calendar'],['scheduleHoursView','hours']]){const selected=view===value;$(id).setAttribute('aria-pressed',String(selected));$(id).classList.toggle('btn-primary',selected);$(id).classList.toggle('btn-light',!selected);}}
    function renderCalendar() {
        const p = state.data.payload;
        const staff = p.staff.filter(e=>!state.selected || String(e.id)===state.selected);
        $('scheduleCalendarTitle').textContent = monthLabel();
        $('scheduleCalendarSubtitle').textContent = departmentLabel() + ' / ' + (state.selected ? employeeById(state.selected)?.name : staff.length+' employees');
        $('scheduleLegend').innerHTML = E.allowedCodes(p).map(code=>'<span>'+badge(code)+' '+esc(shiftDescription(code))+'</span>').join('');
        const days = E.dates(state.month);
        const offset = new Date(days[0]+'T00:00:00Z').getUTCDay();
        const today = new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
        const week = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        let html = week.map(day=>'<div class="schedule-weekday">'+day+'</div>').join('');
        for(let n=0;n<offset;n++) html+='<div class="schedule-day-empty" aria-hidden="true"></div>';
        for(const day of days) {
            const weekday = week[new Date(day+'T00:00:00Z').getUTCDay()];
            const assigned = staff.filter(e=>p.assignments[e.id]?.[day]);
            html += '<article class="schedule-day" data-today="'+(day===today)+'"><header><time datetime="'+day+'"><span>'+Number(day.slice(-2))+'</span><small>'+weekday+'</small></time>'+(p.holidays?.[day]?'<small title="'+esc(p.holidays[day])+'">PH</small>':'')+'<button type="button" class="schedule-day-add" data-edit-date="'+day+'" title="Assign duty" aria-label="Assign duty on '+day+'">'+icon('plus-lg')+'</button></header><div class="schedule-day-list">';
            for(const employee of assigned) {
                const code = p.assignments[employee.id][day];
                const reference=p.exchange_refs?.[employee.id]?.[day];
                html += '<button type="button" class="schedule-assignment" data-edit-duty="'+employee.id+'" data-date="'+day+'" title="'+esc(label(employee)+(reference?' / Exchange of Duty '+reference:'')+(p.requestOffOverrides?.[employee.id]?.[day]?' / Request-Off review: '+p.requestOffOverrides[employee.id][day].reason:''))+'"><span>'+esc(employee.name)+'</span>'+badge(code)+(reference?'<small class="schedule-exchange-ref">â†” '+esc(reference)+'</small>':'')+'</button>';
            }
            if (!assigned.length) html += '<span class="day-empty-label">'+(boot.access.edit?'No assignments':'No final assignment')+'</span>';
            html+='</div></article>';
        }
        for(let n=0;n<(7-(days.length+offset)%7)%7;n++) html+='<div class="schedule-day-empty" aria-hidden="true"></div>';
        $('scheduleCalendar').innerHTML = html;
    }
    function renderDutyList() {
        if(!$('scheduleDutyList'))return;
        const p=state.data.payload,days=E.dates(state.month),staff=p.staff.filter(employee=>!state.selected||String(employee.id)===state.selected);
        $('scheduleDutyList').innerHTML='<table class="schedule-table"><thead><tr><th>FULL NAME</th>'+days.map(day=>'<th>'+Number(day.slice(-2))+'</th>').join('')+'<th>HRS</th></tr></thead><tbody>'+staff.map(employee=>'<tr><td>'+esc(employee.name)+'</td>'+days.map(day=>{const reference=p.exchange_refs?.[employee.id]?.[day],internal=(p.internalLeaveIndicators||[]).find(r=>r.employee_id===employee.id&&r.date===day);return '<td tabindex="0" data-roster-cell="'+employee.id+'" data-date="'+day+'" title="'+esc(day+(internal?' / Internal '+internal.leave_type+' clearance, HR filing outside the system':'')+(reference?' / Exchange of Duty '+reference:''))+'">'+(p.assignments[employee.id]?.[day]?badge(p.assignments[employee.id][day]):'<span class="roster-blank" aria-label="Unassigned">â€”</span>')+(reference?'<small>â†” '+esc(reference)+'</small>':'')+'</td>';}).join('')+'<td>'+E.summarize(employee,p.assignments[employee.id]||{}).hours+'</td></tr>').join('')+'</tbody></table>';
    }
    function renderSummary() {
        $('scheduleSummary').innerHTML = state.analysis.summaries.filter(r=>!state.selected || String(r.id)===state.selected).map(r=>'<tr><td><strong>'+esc(r.name)+'</strong><small>'+esc(position(employeeById(r.id)))+'</small></td><td>'+esc(departmentLabel())+'</td><td>'+((r.counts.AM||0)+(r.counts.DAY12||0))+'</td><td>'+((r.counts.PM||0)+(r.counts.NIGHT12||0))+'</td><td>'+(r.counts.NIGHT8||0)+'</td><td>'+(r.counts.ADJUST8||0)+'</td><td>'+(r.counts.PM8||0)+'</td><td>'+r.off+'</td><td>'+r.leave+'</td><td>'+r.twelve+'</td><td>'+r.eight+'</td><td>'+r.dutyDays+'</td><td><strong>'+r.hours+'</strong></td><td>'+r.leaveCreditHours+'</td><td>'+(r.actualRenderedHours??'Not recorded')+'</td><td>'+r.target+' HOURS</td><td>'+r.remaining+'</td><td>'+r.excess+'</td><td>'+(r.variance>0?'+':'')+r.variance+'</td><td><span class="schedule-badge '+r.status+'">'+summaryStatus(r)+'</span></td></tr>').join('') || '<tr><td colspan="11" class="schedule-empty">No staff in this schedule.</td></tr>';
    }
    function renderChecks() {
        const groups={HOURS:['DEFICIT','EXCESS','ADJUSTMENT','PATTERN'],REST:['REST','CONSECUTIVE','OFF','OFF_BLOCK','BOUNDARY'],ABSENCE:['ABSENCE']};
        const selected=$('scheduleConflictFilter').value;
        const checks=state.analysis.conflicts.filter(c=>(!state.selected||!c.employee_id||String(c.employee_id)===state.selected)&&(!selected||(selected==='OTHER'?!Object.values(groups).flat().includes(c.type):groups[selected].includes(c.type))));
        $('scheduleWarningCount').textContent = state.analysis.conflicts.length;
        $('scheduleConflicts').innerHTML=checks.map(c=>'<div class="schedule-conflict">'+icon('exclamation-circle')+'<div><strong>'+esc(employeeById(c.employee_id)?.name||'Department')+'</strong><span>'+esc(c.date)+'</span><p>'+esc(c.message)+'</p></div></div>').join('')||'<p class="schedule-empty">No checks for this filter.</p>';
    }
    function dutyDialog(id, day) {
        if (!editable()) return;
        if(state.data.payload.exchange_refs?.[id]?.[day]){notice('This duty is protected by approved Exchange of Duty '+state.data.payload.exchange_refs[id][day]+'.','error');return;}
        const employees=state.data.payload.staff.filter(e=>e.active);
        if (!employees.length) return;
        id = id || Number(state.selected) || employees[0].id;
        if (!employees.some(e=>e.id===id)) {notice('This employee is inactive or has transferred. Historical assignments are preserved.','error');return;}
        openDialog('Duty assignment','<form class="schedule-form"><label class="full">Employee<select name="employee">'+employees.map(e=>option(e.id,label(e),id)).join('')+'</select></label><label>Date<input type="date" name="date" min="'+E.dates(state.month)[0]+'" max="'+E.dates(state.month).at(-1)+'" value="'+day+'" required></label><label>Duty / Absence<select name="duty">'+option('','Remove assignment','')+E.allowedCodes(state.data.payload,employeeById(id)).map(code=>{const d=E.duties[code];return option(code,d.label+(d.hours?' / '+d.hours+' hours':''),state.data.payload.assignments[id]?.[day]||'');}).join('')+'</select></label><label class="schedule-check full"><input type="checkbox" name="locked" '+(state.data.payload.locks?.[id]?.[day]?'checked':'')+'> Lock assignment during generation</label><label class="full" data-off-review style="display:none">Request-Off accommodation review (required for a different assignment)<textarea name="offReview" maxlength="1000" rows="2">'+esc(state.data.payload.requestOffOverrides?.[id]?.[day]?.reason||'')+'</textarea></label><div class="assignment-preview full" data-duty-summary aria-live="polite"></div><p class="form-error full" data-error hidden></p><footer><button type="button" class="btn btn-light" data-cancel>Cancel</button><button type="submit" class="btn btn-primary">Apply assignment</button></footer></form>');
        const form=dialog.querySelector('form');
        const preview=()=>{
            const e=employeeById(form.elements.employee.value), assignments={...state.data.payload.assignments[e.id]};
            if(form.elements.duty.value) assignments[form.elements.date.value]=form.elements.duty.value; else delete assignments[form.elements.date.value];
            const source=state.data.payload.requests.find(r=>String(r.employee_id)===String(e.id)&&r.date===form.elements.date.value&&r.type==='REQUEST_OFF'&&r.status==='APPROVED');form.querySelector('[data-off-review]').style.display=source?'grid':'none';form.elements.offReview.required=!!source&&!['OFF','REQUEST_OFF'].includes(form.elements.duty.value);
            const sum=E.summarize(e,assignments);
            form.querySelector('[data-duty-summary]').innerHTML='<strong>'+sum.hours+' / '+sum.target+' HOURS</strong><span class="schedule-badge '+sum.status+'">'+sum.status+'</span><span>'+sum.dutyDays+' duties / '+sum.remaining+' remaining / '+sum.excess+' excess hours</span>';
        };
        const selectDuty=()=>{const id=form.elements.employee.value,date=form.elements.date.value;form.elements.duty.innerHTML=option('','Remove assignment','')+E.allowedCodes(state.data.payload,employeeById(id)).map(code=>option(code,E.duties[code].label+' / '+E.duties[code].hours+' credited hours',state.data.payload.assignments[id]?.[date]||'')).join('');form.elements.locked.checked=!!state.data.payload.locks?.[id]?.[date];form.elements.offReview.value=state.data.payload.requestOffOverrides?.[id]?.[date]?.reason||'';preview();};
        form.elements.employee.onchange=selectDuty; form.elements.date.onchange=selectDuty; form.elements.duty.onchange=preview;
        form.onsubmit=event=>{
            event.preventDefault(); const employee=Number(form.elements.employee.value), date=form.elements.date.value, code=form.elements.duty.value;
            if(state.data.payload.exchange_refs?.[employee]?.[date]){formError(Error('This assignment is protected by approved Exchange of Duty.'));return;}
            if(code&&E.duties[code]?.duration){const own=state.data.payload.assignments[employee]||{};const entries=[...Object.entries(own).filter(([day])=>day!==date).map(([date,duty])=>({date,duty})),...state.data.boundaries.filter(b=>b.employee_id===employee)];if(entries.some(b=>E.incompatible(date,code,b.date,b.duty,state.data.payload.rules.minRest,null,b.shift_definition))){formError(Error('This assignment violates the minimum rest interval or prohibited PM â†’ AM transition.'));return;}}
            const approved=state.data.payload.requests.find(r=>r.employee_id===employee&&r.date===date&&r.status==='APPROVED');
            if(approved?.type==='REQUEST_OFF'){if(!['OFF','REQUEST_OFF'].includes(code)){const reason=form.elements.offReview.value.trim();if(!reason){formError(Error('Enter the authorized accommodation review reason.'));return;}state.data.payload.requestOffOverrides||={};state.data.payload.requestOffOverrides[employee]||={};state.data.payload.requestOffOverrides[employee][date]={reason};}else if(state.data.payload.requestOffOverrides?.[employee])delete state.data.payload.requestOffOverrides[employee][date];}
            else if(approved && code!==approved.type&&!(approved.official_leave&&code===approved.leave_code)){formError(Error('An approved absence exists on this date. Update the authoritative record before changing the assignment.'));return;}
            state.data.payload.assignments[employee]||={};
            state.data.payload.locks||={};state.data.payload.locks[employee]||={};if(form.elements.locked.checked)state.data.payload.locks[employee][date]=true;else delete state.data.payload.locks[employee][date];
            if(code) state.data.payload.assignments[employee][date]=code; else delete state.data.payload.assignments[employee][date];
            closeDialog(); changed();
        }; preview();
    }
    function resetAssignments(){
        const p=state.data.payload;p.locks||={};for(const employee of p.staff){const id=employee.id;p.assignments[id]=Object.fromEntries(Object.entries(p.assignments[id]||{}).filter(([date,code])=>p.exchange_refs?.[id]?.[date]||['SPL','SL','VL','WL','FL','LEAVE','TRAINING','OFFICIAL_BUSINESS'].includes(code)));p.locks[id]=Object.fromEntries(Object.entries(p.locks?.[id]||{}).filter(([date])=>p.exchange_refs?.[id]?.[date]));}p.requestOffOverrides={};
    }
    async function generate(fromSetup = false) {
        if(!editable()||!activeStaff().length) return;
        
        if(fromSetup && !await applySetup()) return;
        const existing=Object.values(state.data.payload.assignments).some(days=>Object.keys(days).length);
        openDialog('Generate monthly schedule','<form class="schedule-form"><p class="full">'+activeStaff().length+' employees / '+esc(monthLabel())+' / 160-hour reference</p>'+(existing?'<label class="schedule-check full"><input type="checkbox" name="replace"> Replace existing duties and OFF entries</label>':'')+'<p class="full generation-facts">Approved absences and entered leave, training, and official business are retained.</p><footer><button type="button" class="btn btn-light" data-cancel>Cancel</button><button type="submit" class="btn btn-primary">Generate schedule</button></footer></form>');
        dialog.querySelector('form').onsubmit=event=>{
            event.preventDefault();
            const payload=clone(state.data.payload);
            payload.rules=noQuotas(payload.rules,payload.staff.length);
            if(event.currentTarget.elements.replace?.checked) for(const employee of payload.staff.filter(e=>e.active)) payload.assignments[employee.id]=Object.fromEntries(Object.entries(payload.assignments[employee.id]||{}).filter(([date,code])=>payload.locks?.[employee.id]?.[date]||payload.exchange_refs?.[employee.id]?.[date]||['REQUEST_OFF','LEAVE','TRAINING','OFFICIAL_BUSINESS','SL','VL','WL','FL','SPL'].includes(code)));
            closeDialog(); showWorkspace(); setBusy(true); $('scheduleCancelGenerate').hidden=false;
            let worker, timer;
            const finish=()=>{clearTimeout(timer);worker?.terminate();state.worker=null;state.cancelGeneration=null;$('scheduleCancelGenerate').hidden=true;setBusy(false);};
            try {
                worker=state.worker=new Worker('../public/js/schedule-worker.js?v=13');
                timer=setTimeout(()=>{finish();notice('Generation timed out. Existing assignments are unchanged.','error');},240000);
                state.cancelGeneration=()=>{finish();notice('Generation cancelled. Existing assignments are unchanged.');};
                worker.onmessage=({data})=>{
                    if(data.progress){notice('Scheduling employee '+(data.progress.current+1)+' of '+data.progress.total+'...');return;}
                    finish(); if(data.error){notice(data.error,'error');return;}
                    state.data.payload=data.result.payload; changed();
                    const complete=state.analysis.summaries.filter(e=>e.status==='COMPLETE').length;
                    notice((data.result.infeasible?'Infeasible proposal; resolve mandatory checks. ':'Generation complete: ')+complete+' of '+activeStaff().length+' staff meeting their duty target (160-hour reference; 168 hours for 12-hour duties). Review remaining deficits and schedule checks.','success');
                };
                worker.onerror=()=>{finish();notice('Generation failed. Manual scheduling remains available.','error');};
                worker.postMessage({payload,month:state.month,boundaries:state.data.boundaries});
            } catch(error){finish();notice(error.message,'error');}
        };
    }
    async function saveAction(action,remarks,ack=false) {
        setBusy(true);
        try {
            const payload=clone(state.data.payload);
            payload.rules=noQuotas(payload.rules,payload.staff.length);
            state.data=normalize(await api({}, {department:state.department,month:state.month,action,version:state.data.version,payload,remarks,reviewAcknowledged:ack,historyReviewed:ack}));
            state.dirty=false; closeDialog();render();notice('Schedule '+state.data.status.toLowerCase()+'.','success');
        } catch(error){formError(error);notice(error.message,'error');}
        finally {setBusy(false);}
    }
    function workflow(action) {
        if(state.busy||!state.data) return;
        if(action==='save'){saveAction(action,'Monthly draft saved');return;}
        const titles={submit:'Finalize schedule for approval',approve:'Approve schedule',return:'Return for revision',publish:'Publish schedule',revise:'Start revision'};
        openDialog(titles[action],'<form class="schedule-form">'+(action==='submit'?'<p class="full">Finalization submits this roster for independent approval. An authorized approver must approve and publish it before it becomes Finalized.</p>':'')+'<p class="full">'+state.analysis.conflicts.length+' schedule checks / '+state.analysis.summaries.filter(e=>e.status==='COMPLETE').length+' staff complete</p><label class="full">Remarks<textarea name="remarks" rows="3" maxlength="1000" '+(['return','revise'].includes(action)?'required':'')+'></textarea></label>'+(['submit','approve','publish'].includes(action)?'<label class="schedule-check full"><input type="checkbox" name="ack" required> Hours, absences, coverage, and missing month-boundary history reviewed</label>':'')+'<p class="full form-error" data-error hidden></p><footer><button type="button" class="btn btn-light" data-cancel>Cancel</button><button type="submit" class="btn btn-primary">'+titles[action]+'</button></footer></form>');
        dialog.querySelector('form').onsubmit=event=>{event.preventDefault();if(state.busy)return;saveAction(action,event.currentTarget.elements.remarks.value.trim()||titles[action],event.currentTarget.elements.ack?.checked||false);};
    }
    async function history() {
        if(!state.data||state.busy) return;
        setBusy(true);
        try {
            const rows=await api({action:'history',department:state.department,month:state.month});
            openDialog('Revision history',rows.map(r=>'<details class="schedule-history"><summary>Version '+r.version+' / '+esc(r.action)+' / '+esc(r.changed_by_name)+'</summary><p>'+esc(r.created_at)+' (Asia/Manila)</p><p>'+esc(r.remarks)+'</p><ul>'+r.changes.map(c=>'<li>'+esc(employeeById(c.employee_id)?.name||'Employee '+c.employee_id)+' / '+esc(c.date)+' / '+esc(E.duties[c.previous]?.label||'Unassigned')+' to '+esc(E.duties[c.next]?.label||'Unassigned')+'</li>').join('')+'</ul></details>').join('')||'<p>No saved revisions.</p>');
        }catch(error){notice(error.message,'error');}finally{setBusy(false);}
    }
    function exportCsv(summary) {
        if(!state.data)return;const p=state.data.payload,days=E.dates(state.month),employees=p.staff.filter(e=>!state.selected||String(e.id)===state.selected);
        const hours=[['Employee','Position','Schedule department','AM','PM','N','AM8','PM8','OFF','Leave days','Scheduled working hours','Authorized leave credits','Actual rendered hours','Target hours','Difference','Status'],...state.analysis.summaries.filter(r=>!state.selected||String(r.id)===state.selected).map(r=>[r.name,employeeById(r.id).position,departmentLabel(),(r.counts.AM||0)+(r.counts.DAY12||0),(r.counts.PM||0)+(r.counts.NIGHT12||0),r.counts.NIGHT8||0,r.counts.ADJUST8||0,r.counts.PM8||0,r.off,r.leave,r.hours,r.leaveCreditHours,r.actualRenderedHours??'Not recorded',r.target,r.variance,summaryStatus(r)])];
        const legend=[['JVGH Nursing Schedule',departmentLabel(),state.month,state.data.status+(state.dirty?' / UNSAVED':'')],['Duty code','Exact shift times / credited hours / meal deduction'],...E.allowedCodes(p).map(code=>[E.duties[code].label,shiftDescription(code)||E.duties[code].label]),[],['Holiday date','Holiday designation'],...Object.entries(p.holidays||{})];
        const sheets=summary?[]:[{name:'Monthly roster',freeze:true,rows:[['FULL NAME',...days.map(day=>Number(day.slice(-2))),'TOTAL HOURS'],...employees.map(e=>[e.name,...days.map(day=>dutyLabel(p.assignments[e.id]?.[day])),E.summarize(e,p.assignments[e.id]||{}).hours])]}];
        sheets.push({name:'Monthly hours',rows:hours,freeze:true},{name:'Duty legend',rows:legend});
        const url=URL.createObjectURL(new Blob([window.NsScheduleExport.workbook(sheets)],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}));const link=document.createElement('a');link.href=url;link.download='JVGH-'+state.department+'-'+state.month+'-'+(summary?'hours':'schedule')+'.xlsx';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
    }
    function selectStaff(id) {state.selected=String(id);render();}
    ['scheduleDepartment','scheduleMonth','scheduleYear'].forEach(id=>$(id).addEventListener('change',()=>{if(!$('scheduleYear').reportValidity()){syncPeriod();return;}let month=$('scheduleYear').value+'-'+$('scheduleMonth').value;if(month<currentMonth)month=currentMonth;load($('scheduleDepartment').value,month);}));
    $('scheduleReload').onclick=()=>load();
    $('scheduleCalendar').onclick=event=>{const b=event.target.closest('[data-edit-duty],[data-edit-date]');if(b)dutyDialog(Number(b.dataset.editDuty),b.dataset.date||b.dataset.editDate);};
    $('scheduleCalendarView').onclick=()=>selectView('calendar');
    $('scheduleListView').onclick=()=>selectView('list');
    $('scheduleHoursView').onclick=()=>selectView('hours');
    $('scheduleDutyList').onclick=event=>{const cell=event.target.closest('[data-roster-cell]');if(cell)dutyDialog(Number(cell.dataset.rosterCell),cell.dataset.date);};
    $('scheduleDutyList').onkeydown=event=>{const cell=event.target.closest('[data-roster-cell]');if(!cell)return;if(['Enter',' '].includes(event.key)){event.preventDefault();dutyDialog(Number(cell.dataset.rosterCell),cell.dataset.date);}else if(['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key)){event.preventDefault();const row=cell.parentElement,index=[...row.children].indexOf(cell),target=event.key==='ArrowLeft'?row.children[index-1]:event.key==='ArrowRight'?row.children[index+1]:event.key==='ArrowUp'?row.previousElementSibling?.children[index]:row.nextElementSibling?.children[index];target?.focus();}};
    $('scheduleDutyList').onpaste=event=>{const cell=event.target.closest('[data-roster-cell]');if(!cell||!editable())return;event.preventDefault();const rows=event.clipboardData.getData('text').trim().split(/\r?\n/).map(r=>r.split('\t'));let tr=cell.parentElement;const first=[...tr.children].indexOf(cell),changes=[];for(const row of rows){if(!tr){notice('Pasted rows exceed the roster.','error');return;}for(let i=0;i<row.length;i++){const target=tr.children[first+i];if(!target?.dataset.rosterCell){notice('Pasted columns exceed the month.','error');return;}const code=E.allowedCodes(state.data.payload,employeeById(target.dataset.rosterCell)).find(c=>c===row[i]||E.duties[c].label===row[i]);if(code&&!E.allowedCodes(state.data.payload,employeeById(target.dataset.rosterCell)).includes(code)){notice('Duty is not eligible for this employee.','error');return;}if(!code&&row[i]){notice('Unknown pasted duty '+row[i],'error');return;}const id=target.dataset.rosterCell,date=target.dataset.date;if(state.data.payload.exchange_refs?.[id]?.[date]){notice('Pasted cells include a protected exchange.','error');return;}changes.push({id,date,code});}tr=tr.nextElementSibling;}const proposal=clone(state.data.payload);for(const {id,date,code} of changes){proposal.assignments[id]||={};if(code)proposal.assignments[id][date]=code;else delete proposal.assignments[id][date];}if(E.analyze(proposal,state.month,state.data.boundaries).conflicts.some(c=>['REST','DUPLICATE'].includes(c.type))){notice('Paste blocked: prohibited transition, overlap or minimum rest violation.','error');return;}for(const {id,date,code} of changes){state.data.payload.assignments[id]||={};if(code)state.data.payload.assignments[id][date]=code;else delete state.data.payload.assignments[id][date];}changed();};
    $('scheduleDutyList').oncopy=event=>{const cell=event.target.closest('[data-roster-cell]');if(cell){event.clipboardData.setData('text/plain',dutyLabel(state.data.payload.assignments[cell.dataset.rosterCell]?.[cell.dataset.date]));event.preventDefault();}};
    $('scheduleConflictFilter').onchange=()=>{if(state.analysis)renderChecks();};
    ['Previous','Next'].forEach((name,index)=>$('schedule'+name).onclick=()=>{const date=new Date(state.month+'-01T00:00:00Z');date.setUTCMonth(date.getUTCMonth()+(index?1:-1));const month=date.toISOString().slice(0,7);if(month>='2000-01'&&month<='2100-12')load(state.department,month);});
    if(boot.access.edit) {
        document.querySelectorAll('[data-setup-step]').forEach(button=>button.onclick=()=>showSetup(Number(button.dataset.setupStep)));
        $('scheduleSetupBack').onclick=()=>showSetup(state.step-1);$('scheduleSetupNext').onclick=()=>showSetup(state.step+1);$('scheduleResume').onclick=()=>showSetup(7);
        $('scheduleSetupRoster').onclick=async event=>{
            const button=event.target.closest('[data-remove-inactive]');if(!button||!editable())return;
            const id=Number(button.dataset.removeInactive),employee=employeeById(id);if(!employee||employee.active||state.data.master.some(e=>e.id===id))return;
            if(state.data.payload.exchange_refs?.[id]){notice('Employee has a protected exchange. Start an authorized correction.','error');return;}
            if(!await confirm('Remove inactive employee?', 'Remove '+employee.name+' from this draft? Recorded revisions and published schedules remain available.'))return;
            state.data.payload.staff=state.data.payload.staff.filter(e=>e.id!==id);
            for(const field of ['assignments','locks','requestOffOverrides'])delete state.data.payload[field]?.[id];
            state.data.payload.requests=state.data.payload.requests.filter(r=>r.employee_id!==id);
            state.data.payload.internalLeaveIndicators=(state.data.payload.internalLeaveIndicators||[]).filter(r=>r.employee_id!==id);
            changed();
        };
        $('scheduleManual').onclick=async()=>{if(!await applySetup())return;const hasEntries=Object.values(state.data.payload.assignments).some(days=>Object.keys(days).length);if(hasEntries&&!await confirm('Open blank manual roster?','Clear editable duties for the selected month? Approved exchange duties and recorded absences remain protected.'))return;resetAssignments();state.data.payload.mode='MANUAL';selectView('list');changed();notice('Blank manual roster opened. Click any date cell to assign duty.');};
        document.querySelectorAll('[name=schedulePattern]').forEach(input=>input.onchange=()=>applySetup());
        document.querySelectorAll('.rest-fields input,.rest-fields select').forEach(input=>input.onchange=()=>applySetup());
        $('scheduleMode').onchange=()=>{state.data.payload.mode=$('scheduleMode').value;changed();};
        $('scheduleReset').onclick=async()=>{if(!editable()||!await confirm('Reset schedule?','Clear editable duties and locks for this draft? Published schedules, approved exchanges and recorded absences are preserved.'))return;resetAssignments();changed();notice('Editable draft duties cleared.');};
        $('scheduleValidate').onclick=async()=>{if(editable()&&!await applySetup())return;render();$('scheduleReview').open=true;$('scheduleReview').scrollIntoView({block:'start'});notice(state.analysis.conflicts.length+' scheduling checks found. Finalization also runs authoritative backend validation.');};
        $('scheduleGenerate').onclick=()=>generate(true);
        $('scheduleWorkflow').onclick=event=>{const b=event.target.closest('[data-workflow]');if(b)workflow(b.dataset.workflow);};
    }
    $('scheduleCancelGenerate').onclick=()=>state.cancelGeneration?.();
    $('scheduleHistory').onclick=history;
    $('schedulePdf').onclick=()=>{document.body.classList.remove('schedule-print-summary');window.print();};
    $('schedulePrint').onclick=()=>{document.body.classList.remove('schedule-print-summary');window.print();};
    $('schedulePrintSummary').onclick=()=>{document.body.classList.add('schedule-print-summary');window.print();};
    $('scheduleExport').onclick=()=>exportCsv(false);
    $('scheduleExportSummary').onclick=()=>exportCsv(true);
    document.querySelectorAll('.schedule-sidebar a[href^="#"]').forEach(link=>link.addEventListener('click',event=>{
        if(link.dataset.setupLink){event.preventDefault();showSetup(Number(link.dataset.setupLink));}
        else if(!link.dataset.scheduleAction && link.getAttribute('href') !== '#scheduleApp'){showWorkspace();}
    }));
    window.addEventListener('afterprint',()=>document.body.classList.remove('schedule-print-summary'));
    window.addEventListener('beforeunload',event=>{if(state.dirty||state.busy){event.preventDefault();event.returnValue='';}});
    window.addEventListener('storage',event=>{if(event.key==='jvgh.exchange.updated'){if(state.dirty)notice('A duty exchange updated the published roster. Save or discard your draft and reload.','error');else load(state.department,state.month);}});
    load().then(()=>{const panel=new URLSearchParams(location.search).get('panel');if(panel==='hours'||panel==='reports')showSetup(8);else if(panel==='employees')showSetup(3);else if(location.hash==='#scheduleEmployeeSetup')showSetup(3);else if(location.hash==='#scheduleWorkflow')showSetup(9);});
    window.addEventListener('hashchange',()=>{if(location.hash==='#scheduleEmployeeSetup')showSetup(3);if(location.hash==='#scheduleWorkflow')showSetup(9);});
})();
