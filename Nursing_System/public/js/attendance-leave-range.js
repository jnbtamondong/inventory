document.addEventListener('DOMContentLoaded', () => {
    const headControls = document.querySelector('.head-controls');
    const masterRows = [...document.querySelectorAll('.summary-table tbody tr')].filter((row) => !row.querySelector('.empty'));
    masterRows.forEach((row) => row.cells[1]?.querySelector('small')?.remove());
    const summaryHeader = document.querySelector('.summary-table thead tr');
    if (summaryHeader && !summaryHeader.querySelector('[data-absent-column]')) {
        const heading = document.createElement('th');
        heading.dataset.absentColumn = '';
        heading.textContent = 'Absent';
        summaryHeader.children[4]?.after(heading);
        masterRows.forEach((row) => {
            const employeeKey = row.cells[0]?.textContent.trim();
            const cell = document.createElement('td');
            cell.dataset.absentColumn = '';
            cell.innerHTML = `<span class="count-value">${Number(window.attendanceAbsentCounts?.[employeeKey] || 0)}</span>`;
            row.cells[4]?.after(cell);
        });
    }
    if (summaryHeader && !summaryHeader.querySelector('[data-late-band]')) {
        const absentHeading = summaryHeader.querySelector('[data-absent-column]');
        const underHeading = document.createElement('th');
        underHeading.dataset.lateBand = 'over15';
        underHeading.textContent = 'Late > 15 Mins';
        const hourHeading = document.createElement('th');
        hourHeading.dataset.lateBand = 'oneHour';
        hourHeading.textContent = 'Late 1 Hour+';
        absentHeading?.before(underHeading, hourHeading);
        masterRows.forEach((row) => {
            const employeeKey = row.cells[0]?.textContent.trim();
            const records = window.attendanceRecordDetails?.[employeeKey] || [];
            const values = {over15:records.filter((record)=>Number(record.late_minutes)>15&&Number(record.late_minutes)<60).length,oneHour:records.filter((record)=>Number(record.late_minutes)>=60).length};
            const absentCell = row.querySelector('[data-absent-column]');
            const underCell = document.createElement('td');
            underCell.dataset.lateBand = 'over15';
            underCell.innerHTML = `<span class="count-value">${Number(values.over15 || 0)}</span>`;
            const hourCell = document.createElement('td');
            hourCell.dataset.lateBand = 'oneHour';
            hourCell.innerHTML = `<span class="count-value">${Number(values.oneHour || 0)}</span>`;
            absentCell?.before(underCell, hourCell);
        });
    }
    if (summaryHeader && !summaryHeader.querySelector('[data-record-action]')) {
        const actionHeading = document.createElement('th');
        actionHeading.dataset.recordAction = '';
        actionHeading.textContent = 'Action';
        summaryHeader.appendChild(actionHeading);
        const modal = document.createElement('dialog');
        modal.className = 'attendance-detail-modal';
        modal.innerHTML = '<div class="attendance-detail-head"><h2 data-detail-employee>Employee</h2><button type="button" class="modal-x" aria-label="Close">×</button></div><div class="attendance-detail-body"><div class="table-wrap"><table><thead><tr><th>Date / Range</th><th>Record Type</th><th>Duty Schedule</th><th>Time In</th><th>Late Minutes</th><th>Leave Type</th><th>Leave Days</th><th>Remarks</th></tr></thead><tbody data-detail-body></tbody></table></div></div>';
        document.body.appendChild(modal);
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
        const formatDate = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'}) : '—';
        const formatTime = (value) => value ? new Date(`2000-01-01T${value}`).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'}) : '—';
        const openDetails = (employeeKey, employeeName) => {
            const records = window.attendanceRecordDetails?.[employeeKey] || [];
            modal.querySelector('[data-detail-employee]').textContent = employeeName;
            const body = modal.querySelector('[data-detail-body]');
            body.innerHTML = records.length ? records.map((record) => {
                const isLeave = record.duty_schedule === 'LEAVE';
                const isAbsent = record.duty_schedule === 'ABSENT';
                const range = isLeave && record.leave_end_date ? `${formatDate(record.record_date)} – ${formatDate(record.leave_end_date)}` : formatDate(record.record_date);
                const recordType = isLeave ? 'LEAVE' : (isAbsent ? 'ABSENT' : 'LATE');
                return `<tr><td>${escapeHtml(range)}</td><td><strong>${recordType}</strong></td><td>${isLeave||isAbsent?'—':escapeHtml(record.duty_schedule)}</td><td>${isLeave||isAbsent?'—':escapeHtml(formatTime(record.actual_time_in))}</td><td>${Number(record.late_minutes||0)}</td><td>${isLeave?escapeHtml(record.leave_type):'—'}</td><td>${isLeave?Math.round(Number(record.leave_days||0)):'—'}</td><td>${escapeHtml(record.remarks||'—')}</td></tr>`;
            }).join('') : '<tr><td colspan="8" class="attendance-detail-empty">No attendance or leave records for the selected period.</td></tr>';
            modal.showModal();
        };
        modal.querySelector('.modal-x').addEventListener('click', () => modal.close());
        modal.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });
        masterRows.forEach((row) => {
            const employeeKey = row.cells[0]?.textContent.trim();
            const employeeName = row.cells[1]?.querySelector('strong')?.textContent.trim() || row.cells[1]?.textContent.trim();
            const cell = document.createElement('td');
            cell.dataset.recordAction = '';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn light';
            button.innerHTML = '<i class="fa-solid fa-eye"></i> View';
            button.addEventListener('click', () => openDetails(employeeKey, employeeName));
            cell.appendChild(button);
            row.appendChild(cell);
        });
    }
    masterRows.forEach((row) => row.querySelectorAll('.leave-value').forEach((value) => { value.textContent = String(Math.round(Number(value.textContent) || 0)); }));
    if (headControls && masterRows.length) {
        const searchWrap = document.createElement('div');
        searchWrap.className = 'master-search';
        searchWrap.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search employee, ID, position, department" aria-label="Search employees">';
        const search = searchWrap.querySelector('input');
        const refresh = () => {
            const query = search.value.trim().toLowerCase();
            let visible = 0;
            masterRows.forEach((row) => { const show = !query || row.textContent.toLowerCase().includes(query); row.hidden = !show; if (show) visible++; });
        };
        search.addEventListener('input', refresh);
        headControls.prepend(searchWrap);
        refresh();
    }
    const form = document.getElementById('encodeAttendanceForm');
    const dialog = document.getElementById('encodeAttendanceModal');
    const encodeButton = [...document.querySelectorAll('.head-controls button')].find((button) => button.textContent.includes('Encode Attendance'));
    dialog?.addEventListener('close', () => setTimeout(() => encodeButton?.blur(), 0));
    const type = document.getElementById('recordType');
    if (type && !type.querySelector('option[value="ABSENT"]')) {
        const absentOption = document.createElement('option');
        absentOption.value = 'ABSENT';
        absentOption.textContent = 'Absent';
        type.appendChild(absentOption);
    }
    const start = form?.querySelector('input[name="record_date"]');
    if (!form || !type || !start) return;

    const startLabel = start.closest('label');
    const lookup = document.getElementById('employeeLookup');
    const dataOptions = [...document.querySelectorAll('#employeeList option')];
    if (lookup && dataOptions.length) {
        lookup.removeAttribute('list');
        const wrap = document.createElement('div');
        wrap.className = 'employee-search-wrap';
        lookup.parentNode.insertBefore(wrap, lookup);
        wrap.appendChild(lookup);
        const panel = document.createElement('div');
        panel.className = 'employee-results';
        const rows = dataOptions.map((option) => {
            const parts = option.value.split(' — ');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'employee-result';
            button.dataset.value = option.value;
            button.dataset.search = option.value.toLowerCase();
            const icon=document.createElement('i');icon.className='fa-solid fa-user-nurse';
            const text=document.createElement('span'),name=document.createElement('strong'),meta=document.createElement('small');
            name.textContent=parts[0]||option.value;meta.textContent=parts.slice(1).join(' · ');text.append(name,meta);button.append(icon,text);
            panel.appendChild(button);
            return button;
        });
        const none = document.createElement('div');
        none.className = 'employee-no-result';
        none.textContent = 'No matching employee found.';
        none.hidden = true;
        panel.appendChild(none);
        wrap.appendChild(panel);
        const filterEmployees = () => {
            const query = lookup.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach((row) => { row.hidden = query !== '' && !row.dataset.search.includes(query); if (!row.hidden) visible++; });
            none.hidden = visible > 0;
            wrap.classList.add('open');
        };
        lookup.addEventListener('focus', filterEmployees);
        lookup.addEventListener('input', filterEmployees);
        rows.forEach((row) => row.addEventListener('click', () => {
            lookup.value = row.dataset.value;
            lookup.dispatchEvent(new Event('input', {bubbles:true}));
            lookup.dispatchEvent(new Event('change', {bubbles:true}));
            wrap.classList.remove('open');
        }));
        document.addEventListener('click', (event) => { if (!wrap.contains(event.target)) wrap.classList.remove('open'); });
    }
    const endLabel = document.createElement('label');
    endLabel.className = 'encode-field leave-field';
    endLabel.hidden = true;
    endLabel.innerHTML = 'End Date *<input type="date" name="leave_end_date" id="leaveEndDate">';
    startLabel.after(endLabel);

    const daysLabel = document.createElement('label');
    daysLabel.className = 'encode-field leave-field';
    daysLabel.hidden = true;
    daysLabel.innerHTML = 'Days of Leave<div class="leave-days-result" id="leaveDaysResult">1 day</div>';
    endLabel.after(daysLabel);
    const end = endLabel.querySelector('input');
    const result = daysLabel.querySelector('#leaveDaysResult');
    const today = start.max;

    function calculateDays() {
        end.min = start.value;
        if (!start.value || !end.value || end.value < start.value) {
            result.textContent = '0 days';
            return;
        }
        const from = new Date(`${start.value}T00:00:00`);
        const to = new Date(`${end.value}T00:00:00`);
        const days = Math.floor((to - from) / 86400000) + 1;
        result.textContent = `${days} ${days === 1 ? 'day' : 'days'}`;
    }

    function updateRange() {
        const leave = type.value === 'LEAVE';
        endLabel.hidden = !leave;
        daysLabel.hidden = !leave;
        end.required = leave;
        start.max = leave ? '' : today;
        startLabel.childNodes[0].nodeValue = leave ? 'Start Date *' : 'Record Date *';
        if (leave && !end.value) end.value = start.value;
        if (!leave) end.value = '';
        calculateDays();
    }

    type.addEventListener('change', updateRange);
    start.addEventListener('change', () => {
        if (end.value && end.value < start.value) end.value = start.value;
        calculateDays();
    });
    end.addEventListener('change', calculateDays);
    updateRange();
});
