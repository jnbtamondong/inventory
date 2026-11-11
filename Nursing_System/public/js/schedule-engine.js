/* Pure calendar calculations shared by the UI, worker, and regression tests. */
(function (root, factory) {
    const engine = factory();
    if (typeof module === 'object' && module.exports) module.exports = engine;
    else root.NsSchedule = engine;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    let TARGET = 160;
    const duties = {
        AM: { label: 'AM', hours: 8, start: 6, duration: 8 },
        PM: { label: 'PM', hours: 8, start: 14, duration: 8 },
        NIGHT8: { label: 'N', hours: 8, start: 22, duration: 8 },
        DAY12: { label: 'AM', hours: 12, start: 6, duration: 12 },
        NIGHT12: { label: 'PM', hours: 12, start: 18, duration: 12 },
        ADJUST8: { label: 'AM8', hours: 8, start: 6, duration: 8 },
        OFF: { label: 'OFF', hours: 0, start: 0, duration: 0 },
        REQUEST_OFF: { label: 'Request off', hours: 0, start: 0, duration: 0 },
        LEAVE: { label: 'Leave', hours: 0, start: 0, duration: 0 },
        TRAINING: { label: 'Training', hours: 0, start: 8, duration: 8 },
        OFFICIAL_BUSINESS: { label: 'Official business', hours: 0, start: 8, duration: 8 }
    };
    Object.assign(duties,{PM8:{label:'PM8',hours:8,start:14,duration:8},'6':{label:'6',hours:8,start:6,duration:9},'7':{label:'7',hours:8,start:7,duration:9},'8':{label:'8',hours:8,start:8,duration:9},M:{label:'M',hours:10,start:7,duration:11}});
    for(const code of ['PH','SPL','SL','VL','WL','FL','ML','PL','SOLO_PARENT','OTHER_LEAVE'])duties[code]={label:code,hours:0,start:0,duration:0};
    const clinical = Object.keys(duties).filter(key => duties[key].hours);
    let fixedMode=false,leaveCredits={},officialLeaveDays={};
    function configure(payload){TARGET=Number(payload.rules?.target??payload.policy?.target??160);fixedMode=payload.policy?.mode==='FIXED';leaveCredits=payload.leaveCredits||{};officialLeaveDays={};for(const r of payload.requests||[])if(r.official_leave&&r.status==='APPROVED'){officialLeaveDays[r.employee_id]||={};officialLeaveDays[r.employee_id][r.date]=r.leave_code;}if(payload.shiftMaster)Object.assign(duties,payload.shiftMaster);clinical.splice(0,clinical.length,...Object.keys(duties).filter(code=>duties[code].hours));}
    function allowedCodes(payload,employee=null){
        const absences=['OFF','REQUEST_OFF','PH','SPL','SL','VL','WL','FL','ML','PL','SOLO_PARENT','OTHER_LEAVE','LEAVE','TRAINING','OFFICIAL_BUSINESS'];
        if(payload.policy?.mode==='FIXED'||employee&&fixedEmployee(employee))return (payload.policy?.department==='OPD'?['6','7','8','M']:['8','M']).concat(absences.filter(code=>code!=='REQUEST_OFF'));
        const patterns=employee?[employee.pattern]:payload.staff.filter(e=>!fixedEmployee(e)).map(e=>e.pattern);if(!patterns.length)patterns.push('8');
        let codes=payload.policy?.department==='OR'&&payload.policy.requiredShifts?.length?[...payload.policy.requiredShifts]:[...(patterns.includes('8')?['AM','PM','NIGHT8']:[]),...(patterns.includes('12')?['DAY12','NIGHT12']:[])];
        if(!employee&&payload.staff.some(fixedEmployee))codes.push('8','M');return [...new Set(codes.concat(absences))];
    }
    const staffingTargets = { TRIAGE: 1, NURSE: 5, NA: 1 };
    const category = employee => employee.category || (/\b(nurs(?:e|ing)\s+attendant|NA)\b/i.test(employee.position || '') ? 'NA' : 'NURSE');
    const fixedEmployee=employee=>['SUPERVISOR_NURSE','SUPERVISOR','ASSISTANT_CHIEF_NURSE','CHIEF_NURSE'].includes(employee.role);
    const staffingRole = employee => category(employee) === 'NA' ? 'NA' : employee.staffingRole === 'TRIAGE' ? 'TRIAGE' : 'NURSE';
    const timestamp = day => Date.parse(day + 'T00:00:00Z');
    const dateString = date => new Date(date).toISOString().slice(0, 10);
    const addDays = (day, amount) => dateString(timestamp(day) + amount * 86400000);
    function dates(month) {
        const [year, m] = month.split('-').map(Number);
        return Array.from({ length: new Date(Date.UTC(year, m, 0)).getUTCDate() }, (_, i) => `${month}-${String(i + 1).padStart(2, '0')}`);
    }
    function interval(day, duty, definition=null) {
        const type = definition||duties[duty];
        if (!type?.duration) return null;
        const start = timestamp(day) / 3600000 + type.start;
        return [start, start + type.duration];
    }
    function incompatible(aDay, aDuty, bDay, bDuty, rest,aDefinition=null,bDefinition=null) {
        let a = interval(aDay, aDuty,aDefinition), b = interval(bDay, bDuty,bDefinition);
        if (!a || !b) return false;
        if (a[0] > b[0]) [a, b] = [b, a];
        const pm=['PM','PM8','NIGHT12'],am=['AM','ADJUST8','DAY12'];
        if(!fixedMode&&((addDays(aDay,1)===bDay&&aDuty==='DAY12'&&bDuty==='NIGHT12')||(addDays(bDay,1)===aDay&&bDuty==='DAY12'&&aDuty==='NIGHT12')))return true;
        if(!fixedMode&&((addDays(aDay,1)===bDay&&pm.includes(aDuty)&&am.includes(bDuty))||(addDays(bDay,1)===aDay&&pm.includes(bDuty)&&am.includes(aDuty))))return true;
        return b[0] - a[1] < rest;
    }
    function dutyTarget(employee){return employee.pattern==='12'&&!fixedMode&&!fixedEmployee(employee)?Math.ceil(TARGET/12)*12:TARGET;}
    function summarize(employee, assignments = {}) {
        const counts = {};
        for (const code of Object.values(assignments)) counts[code] = (counts[code] || 0) + 1;
        let hours = 0, eight = 0, twelve = 0;
        for (const code of clinical) {
            const count = counts[code] || 0;
            hours += count * duties[code].hours;
            if (duties[code].hours === 8) eight += count; else if(duties[code].hours===12)twelve += count;
        }
        return { id: employee.id, name: employee.name, pattern: employee.pattern, eight, twelve,
            counts,leaveCreditHours:Object.entries(officialLeaveDays[employee.id]||{}).reduce((sum,[date,code])=>sum+(!duties[assignments[date]]?.hours?(leaveCredits[code]||0):0),0),actualRenderedHours:null,dutyDays:clinical.reduce((sum,code)=>sum+(counts[code]||0),0), off: (counts.OFF || 0) + (counts.REQUEST_OFF || 0), leave: ['LEAVE','SL','VL','SPL','WL','FL','ML','PL','SOLO_PARENT','OTHER_LEAVE'].reduce((sum,code)=>sum+(counts[code]||0),0),
            training: counts.TRAINING || 0, business: counts.OFFICIAL_BUSINESS || 0,
            hours, target: TARGET, remaining: Math.max(0, TARGET - hours), excess: Math.max(0, hours - TARGET), variance: hours - TARGET,
            dutyTarget:dutyTarget(employee),approvedOvertimeHours:0,status: hours === dutyTarget(employee) ? 'COMPLETE' : hours < dutyTarget(employee) ? 'DEFICIT' : 'EXCESS' };
    }
    function analyze(payload, month, boundaries = [], personal = false) {
        configure(payload);
        const days = dates(month), conflicts = [], coverage = {}, roleCoverage = {};
        const summaries = payload.staff.map(employee => summarize(employee, payload.assignments[employee.id]));
        const add = (type, employee, date, message) => conflicts.push({ type, employee_id: employee?.id || null, date, message });
        for (const day of days) coverage[day] = Object.fromEntries(clinical.map(code => [code, 0]));
        for (const day of days) roleCoverage[day] = Object.fromEntries(clinical.map(code => [code, { TRIAGE: 0, NURSE: 0, NA: 0 }]));
        for (const employee of payload.staff) {
            const own = payload.assignments[employee.id] || {};
            const external = boundaries.filter(item => item.employee_id === employee.id);
            const entries = [...Object.entries(own).map(([date, duty]) => ({ date, duty })), ...external].sort((a, b) => a.date.localeCompare(b.date));
            const working = entries.filter(item => (item.shift_definition||duties[item.duty])?.duration);
            const workingDates = new Set(working.map(item => item.date));
            for (const [day, duty] of Object.entries(own)) {
                if (duties[duty]?.hours && coverage[day]) {
                    coverage[day][duty]++;
                    roleCoverage[day][duty][staffingRole(employee)]++;
                    if (!employee.active) add('INACTIVE', employee, day, 'Duty assigned to an inactive or transferred employee.');
                    if (!fixedMode && !fixedEmployee(employee) && ((employee.pattern === '8' && duties[duty].hours === 12) || (employee.pattern === '12' && duties[duty].hours === 8 ))) add('PATTERN', employee, day, 'Duty differs from the selected pattern.');
                }
            }
            for (const request of payload.requests.filter(item => item.employee_id === employee.id && item.status === 'APPROVED')) {
                if(request.type==='REQUEST_OFF'&&payload.requestOffOverrides?.[employee.id]?.[request.date]?.reason)continue;
                if(['TRAINING','OFFICIAL_BUSINESS'].includes(request.type)&&own[request.date]===request.type)continue;
                if (duties[own[request.date]]?.hours) add('ABSENCE', employee, request.date, `Duty on approved ${duties[request.type].label.toLowerCase()}.`);
            }
            for (let i = 0; i < working.length; i++) {
                for (let j = i + 1; j < working.length; j++) {
                    const a = working[i], b = working[j];
                    if (timestamp(b.date) - timestamp(a.date) > 4 * 86400000) break;
                    if (!a.date.startsWith(month) && !b.date.startsWith(month)) continue;
                    if (a.date === b.date) add('DUPLICATE', employee, b.date, 'Duty also assigned in another unit on this date.');
                    else if (incompatible(a.date, a.duty, b.date, b.duty, payload.rules.minRest,a.shift_definition,b.shift_definition)) add('REST', employee, b.date, `Less than ${payload.rules.minRest} hours between duties.`);
                }
            }
            for (const day of days) {
                const run = Array.from({ length: payload.rules.maxConsecutive + 1 }, (_, i) => addDays(day, -i));
                if (run.every(date => workingDates.has(date))) add('CONSECUTIVE', employee, day, `More than ${payload.rules.maxConsecutive} consecutive duty days.`);
                const week = Array.from({ length: 7 }, (_, i) => addDays(day, -i));
                const recorded = week.map(date => own[date] || external.find(item => item.date === date)?.duty);
                if (recorded.every(Boolean) && recorded.filter(code => ['OFF', 'REQUEST_OFF', 'LEAVE'].includes(code)).length < payload.rules.offPer7) add('OFF', employee, day, `Fewer than ${payload.rules.offPer7} OFF/rest days in seven days.`);
            }
            const known = Object.fromEntries([...external.map(item => [item.date, item.duty]), ...Object.entries(own)]);
            let restRun = [];
            for (let cursor = addDays(days[0], -14); cursor <= addDays(days.at(-1), 14); cursor = addDays(cursor, 1)) {
                const code = known[cursor];
                if (['OFF', 'REQUEST_OFF', 'LEAVE'].includes(code)) restRun.push(cursor);
                else {
                    const inMonth = restRun.some(date => date.startsWith(month));
                    const before = restRun.length ? known[addDays(restRun[0], -1)] : null;
                    if (inMonth && duties[before]?.duration && duties[code]?.duration && restRun.length < payload.rules.minOff) add('OFF_BLOCK', employee, restRun[0], `Fewer than ${payload.rules.minOff} consecutive days off between duty blocks.`);
                    if (inMonth && restRun.length > payload.rules.maxOff && restRun.every(date => known[date] !== 'LEAVE')) add('OFF_BLOCK', employee, restRun.find(date => date.startsWith(month)), `More than ${payload.rules.maxOff} consecutive days off.`);
                    restRun = [];
                }
            }
            const summary = summaries.find(item => item.id === employee.id);
            if (summary.status!=='COMPLETE') add(summary.status, employee, '', `${summary.hours} / ${TARGET} hours (${summary.variance > 0 ? '+' : ''}${summary.variance}).`);
            if (!fixedMode && !fixedEmployee(employee) && employee.pattern === '12' && summary.eight > 0) add('ADJUSTMENT', employee, '', 'Eight-hour adjustment duties are not allowed in a twelve-hour pattern.');
            const blanks = days.filter(day => !own[day]).length;
            if (blanks) add('UNASSIGNED', employee, '', `${blanks} dates have no assignment or OFF status.`);
            if (!external.some(item => item.date === addDays(days[0], -1)) || !external.some(item => item.date === addDays(days.at(-1), 1))) add('BOUNDARY', employee, '', 'Adjacent-month final records are incomplete; boundary rest needs review.');
        }
        let unfilled = 0;
        if (!personal) for (const day of days) for (const code of clinical) {
            if((fixedMode||payload.policy?.department==='OR')&&(!payload.policy.operatingDays.includes(new Date(day+'T00:00:00Z').getUTCDay())||fixedMode&&payload.holidays?.[day]))continue;
            const bounds = payload.rules.coverage[code] || {min:0,max:200};
            const count = payload.policy?.requiredShifts?.length ? roleCoverage[day][code].NURSE+roleCoverage[day][code].TRIAGE : coverage[day][code];
            if (count < bounds.min) { unfilled += bounds.min - count; add('UNDERSTAFFED', null, day, `${duties[code].label}: ${count} assigned; minimum ${bounds.min}.`); }
            if (count > bounds.max) add('OVERSTAFFED', null, day, `${duties[code].label}: ${count} assigned; maximum ${bounds.max}.`);
        }
        if (!payload.rules.reviewed && !personal) add('RULES', null, '', 'Staffing and rest rules have not been reviewed.');
        if (!personal) for (const day of days) for (const code of payload.rules.staffingShifts || []) {
            let roleMissing = 0;
            for (const [role, target] of Object.entries(payload.rules.staffingTargets || staffingTargets)) {
                const count = roleCoverage[day][code][role];
                const label = role === 'TRIAGE' ? 'triage nurse' : role === 'NA' ? 'nurse attendant' : 'nurses';
                if (count < target) { roleMissing += target - count; add('UNDERSTAFFED', null, day, `${duties[code].label}: ${count}/${target} ${label}.`); }
                if (count > target) add('OVERSTAFFED', null, day, `${duties[code].label}: ${count}/${target} ${label}.`);
            }
            unfilled += Math.max(0, roleMissing - Math.max(0, payload.rules.coverage[code].min - coverage[day][code]));
        }
        return { summaries, conflicts, coverage, roleCoverage, unfilled, totalHours: summaries.reduce((sum, row) => sum + row.hours, 0) };
    }
    // Solve one employee at a time to keep browser MILP models bounded. Coverage
    // deficits guide each solve; this is a proposal, not a global optimality claim.
    function generate(payload, month, boundaries, solver, onProgress = () => {}) {
        configure(payload);
        if(payload.policy?.department==='OR'&&!payload.policy.requiredShifts?.length)throw Error('Configure the Operating Room operational shifts before automatic generation.');
        if(payload.policy?.mode==='FIXED')return generateFixed(payload,month,boundaries);
        const output = JSON.parse(JSON.stringify(payload));
        const targets = output.rules.staffingTargets || staffingTargets;
        if (!output.rules.reviewed) throw Error('Review the scheduling rules before auto-generation.');
        const days = dates(month), notes = [];
        const requiredCoverage=output.policy?.requiredShifts?.length||Object.values(output.rules.coverage).some(bound=>bound.min>0)||(output.rules.staffingShifts||[]).length;
        for (const request of output.requests) if (request.status === 'APPROVED') {
            if(request.type==='REQUEST_OFF'&&output.requestOffOverrides?.[request.employee_id]?.[request.date]?.reason)continue;
            output.assignments[request.employee_id] ||= {};
            output.assignments[request.employee_id][request.date] = request.type;
        }
        const ordered = [...output.staff].sort((a, b) => (category(a)==='NA')-(category(b)==='NA') || a.group.localeCompare(b.group) || a.id - b.id);
        ordered.forEach((employee, index) => {
            onProgress(index, ordered.length);
            if (!employee.active) return;
            const own = output.assignments[employee.id] ||= {};
            if(fixedEmployee(employee)){const code=employee.fixedShift||'8';for(const day of days){if(own[day])continue;own[day]=output.holidays?.[day]?'PH':(output.policy?.operatingDays||[1,2,3,4,5]).includes(new Date(day+'T00:00:00Z').getUTCDay())?code:'OFF';}return;}
            const summary = summarize(employee, own);
            const cycle = output.rules.maxConsecutive + output.rules.minOff;
            const monthDutyLimit = Math.floor(days.length / cycle) * output.rules.maxConsecutive + Math.min(output.rules.maxConsecutive, days.length % cycle);
            const employeeTarget=dutyTarget(employee);
            const maxEight = Math.max(0, (employee.pattern === '8' ? Math.min(Math.floor(TARGET/8), monthDutyLimit) : 0) - summary.eight);
            const maxTwelve = Math.max(0, (employee.pattern === '12' ? Math.ceil(TARGET/12) : 0) - summary.twelve);
            const allowed = (output.policy?.department==='OR'?output.policy.requiredShifts.filter(code=>employee.pattern!=='12'||['DAY12','NIGHT12'].includes(code)):(employee.pattern === '8' ? ['AM', 'PM', 'NIGHT8'] : ['DAY12', 'NIGHT12'])).filter(code=>!employee.shiftEligibility?.length||employee.shiftEligibility.includes(code));
            const customHours=allowed.some(code=>![8,12].includes(duties[code].hours));
            const fixed = [...Object.entries(own).map(([date, duty]) => ({ date, duty })), ...boundaries.filter(item => item.employee_id === employee.id)];
            const analysis = analyze(output, month, boundaries), counts = analysis.coverage;
            const model = { optimize: 'score', opType: 'max', constraints: { eight: { max: maxEight }, twelve: { max: maxTwelve }, hours: { max: Math.max(0, employeeTarget - summary.hours) } }, variables: {}, binaries: {}, options: { timeout: 2000, tolerance: 0 } };
            if(customHours)model.constraints={hours:{max:Math.max(0,employeeTarget-summary.hours)},special:{max:Math.max(0,1-(summary.counts.ADJUST8||0)-(summary.counts.PM8||0))}};
            const regular=allowed.filter(code=>!['ADJUST8','PM8'].includes(code));
            const fairCap=Math.ceil((employee.pattern==='12'?Math.ceil(TARGET/12):Math.floor(TARGET/8))/Math.max(1,regular.length));
            for(const code of regular){const key='fair_'+code;model.constraints[key]={max:Math.max(0,fairCap-(summary.counts[code]||0))};model.variables['fair_overflow_'+code]={score:-20,[key]:-1};}
            const choices = [];
            const groupOffset = [...employee.group].reduce((sum, letter) => sum + letter.charCodeAt(0), index);
            for (let d = 0; d < days.length; d++) {
                const day = days[d];
                if(output.policy?.department==='OR'&&!output.policy.operatingDays.includes(new Date(day+'T00:00:00Z').getUTCDay()))continue;
                if (own[day] || fixed.some(item => item.date === day && duties[item.duty]?.duration)) continue;
                model.constraints['date' + d] = { max: 1 };
                for (const code of allowed) {
                    if (!requiredCoverage && employee.preference !== 'ANY' && code !== employee.preference && !['ADJUST8','PM8'].includes(code)) continue;
                    if (!customHours && (!maxEight && duties[code].hours === 8 || !maxTwelve && duties[code].hours === 12)) continue;
                    if (counts[day][code] >= output.rules.coverage[code].max) continue;
                    const role = staffingRole(employee), roleCount = analysis.roleCoverage[day][code][role];
                    const roleRequired = (output.rules.staffingShifts || []).includes(code);
                    if (roleRequired && roleCount >= targets[role]) continue;
                    if (fixed.some(item => incompatible(day, code, item.date, item.duty, output.rules.minRest,null,item.shift_definition))) continue;
                    const name = `x${choices.length}`;
                    const shortage = Math.max(0, roleRequired ? targets[role] - roleCount : output.rules.coverage[code].min - counts[day][code]);
                    const spread = 1 - ((d + groupOffset) % 5) / 10;
                    const balance = 8 / (1 + counts[day][code]) + 4 / (1 + Object.values(counts[day]).reduce((a, b) => a + b, 0));
                    const variable = { score: 100 + shortage * 30 + spread + balance + (employee.preference===code?10:0), hours: duties[code].hours, ['date' + d]: 1, [duties[code].hours === 8 ? 'eight' : 'twelve']: 1 };
                    if(customHours){delete variable.eight;delete variable.twelve;if(['ADJUST8','PM8'].includes(code))variable.special=1;}
                    if(regular.includes(code)){variable['fair_'+code]=1;variable.score+=4/(1+fixed.filter(item=>!item.date.startsWith(month)&&item.duty===code).length);}
                    model.variables[name] = variable; model.binaries[name] = 1;
                    choices.push({ name, day, code, d });
                }
            }
            // A rolling window includes known final assignments from neighboring months.
            for (const [length, max, prefix] of [[output.rules.maxConsecutive + 1, output.rules.maxConsecutive, 'run'], [cycle, output.rules.maxConsecutive, 'cycle'], [7, 7 - output.rules.offPer7, 'off']]) {
                for (let start = -length + 1; start < days.length; start++) {
                    const windowDates = Array.from({ length }, (_, n) => addDays(days[0], start + n));
                    const fixedCount = new Set(fixed.filter(item => windowDates.includes(item.date) && duties[item.duty]?.duration).map(item => item.date)).size;
                    const key = prefix + start;
                    model.constraints[key] = { max: Math.max(0, max - fixedCount) };
                    for (const choice of choices) if (windowDates.includes(choice.day)) model.variables[choice.name][key] = 1;
                }
            }
            for (let i = 0; i < choices.length; i++) for (let j = i + 1; j < choices.length; j++) {
                const a = choices[i], b = choices[j];
                if (a.day === b.day || b.d - a.d > 3 || !incompatible(a.day, a.code, b.day, b.code, output.rules.minRest)) continue;
                const key = `rest${i}_${j}`;
                model.constraints[key] = { max: 1 }; model.variables[a.name][key] = 1; model.variables[b.name][key] = 1;
            }
            const fixedWorking = new Set(fixed.filter(item => duties[item.duty]?.duration).map(item => item.date));
            const knownOutside = new Set(fixed.map(item => item.date));
            // Forbid work / one day off / work. Unknown neighboring dates are not
            // presumed to be rest; those boundaries remain explicit review warnings.
            for (let d = -2; d < days.length; d++) {
                if (output.rules.minOff < 2) break;
                const sequence = [0, 1, 2].map(n => addDays(days[0], d + n));
                if (sequence.some(day => !day.startsWith(month) && !knownOutside.has(day))) continue;
                const signs = [1, -1, 1];
                const constant = sequence.reduce((sum, day, i) => sum + (fixedWorking.has(day) ? signs[i] : 0), 0);
                const key = `offBlock${d}`;
                model.constraints[key] = { max: Math.max(0, 1 - constant) };
                for (const choice of choices) {
                    const position = sequence.indexOf(choice.day);
                    if (position >= 0) model.variables[choice.name][key] = signs[position];
                }
            }
            for (let d = -output.rules.maxOff; d < days.length && output.rules.maxOff < days.length; d++) {
                const sequence = Array.from({ length: output.rules.maxOff + 1 }, (_, n) => addDays(days[0], d + n));
                if (sequence.some(day => !day.startsWith(month) && !knownOutside.has(day))) continue;
                // Long approved leave is not shortened to satisfy an OFF-block target.
                if (fixed.some(item => sequence.includes(item.date) && item.duty === 'LEAVE')) continue;
                const key = `maxOff${d}`;
                model.constraints[key] = { min: Math.max(0, 1 - sequence.filter(day => fixedWorking.has(day)).length) };
                for (const choice of choices) if (sequence.includes(choice.day)) model.variables[choice.name][key] = 1;
            }
            let accepted = false;
            if (choices.length) {
                // First request the exact monthly pattern, then allow a reported deficit.
                const exact = JSON.parse(JSON.stringify(model));
                if(customHours)exact.constraints.hours={equal:Math.max(0,employeeTarget-summary.hours)};else{exact.constraints.eight = { equal: maxEight }; exact.constraints.twelve = { equal: maxTwelve };}
                let result = solver.Solve(exact);
                if (!result.feasible) result = solver.Solve(model);
                const checkedSelection=(solution,candidate)=>{
                    if(!solution.feasible)return null;
                    const selected = choices.filter(choice => Math.abs((solution[choice.name] || 0) - 1) < 1e-6);
                    const valid = Object.entries(candidate.constraints).every(([key, bound]) => {
                        const value = selected.reduce((sum, choice) => sum + (candidate.variables[choice.name][key] || 0), 0)+Object.entries(candidate.variables).filter(([name])=>!candidate.binaries[name]).reduce((sum,[name,variable])=>sum+(variable[key]||0)*(solution[name]||0),0);
                        return value <= (bound.max ?? Infinity) + 1e-6 && value >= (bound.min ?? -Infinity) - 1e-6;
                    });
                    return valid?selected:null;
                };
                let selected=checkedSelection(result,model);
                if(!selected){
                    // Shift distribution is a preference. If its soft overflow variables
                    // exhaust the solver budget, retry with every safety constraint intact.
                    const fallback=JSON.parse(JSON.stringify(model));
                    for(const key of Object.keys(fallback.constraints))if(key.startsWith('fair_'))delete fallback.constraints[key];
                    for(const name of Object.keys(fallback.variables)){
                        if(name.startsWith('fair_overflow_')){delete fallback.variables[name];continue;}
                        for(const key of Object.keys(fallback.variables[name]))if(key.startsWith('fair_'))delete fallback.variables[name][key];
                    }
                    fallback.options={timeout:3000,tolerance:0};
                    selected=checkedSelection(solver.Solve(fallback),fallback);
                }
                if(selected){selected.forEach(choice=>{own[choice.day]=choice.code;});accepted=true;}
                else notes.push(`${employee.name}: no valid proposal found within the time limit; manual review required.`);
            }
            for (const day of days) own[day] ||= 'OFF';
            const finalSummary = summarize(employee, own);
            if (finalSummary.hours < employeeTarget) notes.push(`${employee.name}: ${finalSummary.remaining} hours remain after respecting absences and rest.`);
        });
        output.mode = 'AUTO';
        const checks=analyze(output,month,boundaries);const mandatory=checks.conflicts.filter(c=>!['BOUNDARY'].includes(c.type));
        return { payload: output, notes, infeasible:mandatory.length>0, conflicts:mandatory };
    }
    function generateFixed(payload,month,boundaries=[]){
        const output=JSON.parse(JSON.stringify(payload));configure(output);output.mode='FIXED';
        for(const employee of output.staff){if(!employee.active)continue;const code=employee.fixedShift||'8';if(!allowedCodes(output).includes(code)||!duties[code]?.hours)throw Error(employee.name+': configure an eligible fixed shift.');const own=output.assignments[employee.id]||={};
            for(const day of dates(month)){if(output.locks?.[employee.id]?.[day]||output.exchange_refs?.[employee.id]?.[day])continue;const unavailable=output.requests.find(r=>r.employee_id===employee.id&&r.date===day&&r.status==='APPROVED');
                own[day]=unavailable&&!(unavailable.type==='REQUEST_OFF'&&output.requestOffOverrides?.[employee.id]?.[day]?.reason)?unavailable.type:(output.holidays?.[day]?'PH':output.policy.operatingDays.includes(new Date(day+'T00:00:00Z').getUTCDay())?code:'OFF');
            }
        }
        const analysis=analyze(output,month,boundaries);return {payload:output,notes:analysis.conflicts.map(c=>c.message),infeasible:analysis.conflicts.some(c=>!['BOUNDARY','DEFICIT','EXCESS','PATTERN','ADJUSTMENT'].includes(c.type))};
    }
    return { configure, allowedCodes, generateFixed, TARGET, duties, clinical, dates, addDays, summarize, analyze, generate, incompatible, category, staffingRole, staffingTargets };
});
