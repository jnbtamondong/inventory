const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const E = require('../public/js/schedule-engine.js');
const context = { self: {} };
vm.createContext(context);
vm.runInContext(fs.readFileSync(require.resolve('../public/vendor/javascript-lp-solver/solver.js'), 'utf8'), context);
const solver = context.self.solver;
const staff = (id, pattern = '12') => ({ id, name: `Synthetic Nurse ${id}`, pattern, preference: 'ANY', group: `Group ${id % 2}`, active: true });
function payload(employees = [staff(1)]) {
    return { staff: employees, assignments: {}, requests: [], mode: 'MANUAL', rules: { minRest: 12, maxConsecutive: 4, minOff: 2, maxOff: 3, offPer7: 2, reviewed: true, coverage: Object.fromEntries(E.clinical.map(code => [code, { min: 0, max: employees.length }])) } };
}
let checks = 0;
function check(name, fn) { fn(); checks++; console.log('PASS ' + name); }
check('calendar leap years and month boundaries', () => {
    assert.equal(E.dates('2024-02').length, 29); assert.equal(E.dates('2026-02').length, 28);
    assert.equal(E.addDays('2026-12-31', 1), '2027-01-01');
});
check('20 eight-hour duties equal 160; removal updates deficit', () => {
    const shifts = Object.fromEntries(E.dates('2026-10').slice(0, 20).map(day => [day, 'AM']));
    assert.equal(E.summarize(staff(1, '8'), shifts).status, 'COMPLETE');
    shifts['2026-10-01'] = 'LEAVE';
    assert.equal(E.summarize(staff(1, '8'), shifts).variance, -8);
});
check('14 twelve-hour duties meet pattern; eight-hour adjustment is rejected', () => {
    const p = payload(); p.assignments[1] = Object.fromEntries(E.dates('2026-10').slice(0, 14).map(day => [day, 'DAY12']));
    assert.equal(E.analyze(p, '2026-10').summaries[0].hours, 168); assert.equal(E.analyze(p, '2026-10').summaries[0].status,'COMPLETE'); assert.equal(E.analyze(p, '2026-10').summaries[0].approvedOvertimeHours,0);
    p.assignments[1]['2026-10-16'] = 'ADJUST8';
    assert(E.analyze(p, '2026-10').conflicts.some(item => item.type === 'ADJUSTMENT'));
    assert.equal(E.analyze(p, '2026-10').summaries[0].status, 'EXCESS');
});
check('overnight rest includes previous-month final shifts', () => {
    const p = payload(); p.assignments[1] = { '2026-10-01': 'DAY12' };
    assert(E.analyze(p, '2026-10', [{ employee_id: 1, date: '2026-09-30', duty: 'NIGHT12' }]).conflicts.some(item => item.type === 'REST'));
});
check('consecutive twelve-hour AM and PM are forbidden in both directions',()=>{
    E.configure(payload());
    assert(E.incompatible('2035-01-01','DAY12','2035-01-02','NIGHT12',0));
    assert(E.incompatible('2035-01-01','NIGHT12','2035-01-02','DAY12',0));
});
check('approved requests conflict with manual duties; unapproved requests do not', () => {
    const p = payload(); p.assignments[1] = { '2026-10-01': 'DAY12' };
    p.requests = [{ employee_id: 1, date: '2026-10-01', type: 'LEAVE', status: 'APPROVED', remarks: '' }];
    assert(E.analyze(p, '2026-10').conflicts.some(item => item.type === 'ABSENCE'));
    p.requests[0].status = 'PENDING'; assert(!E.analyze(p, '2026-10').conflicts.some(item => item.type === 'ABSENCE'));
});
check('consecutive duties and single-day OFF blocks detected', () => {
    const p = payload(); p.assignments[1] = { '2026-10-01': 'DAY12', '2026-10-02': 'OFF', '2026-10-03': 'DAY12', '2026-10-04': 'DAY12', '2026-10-05': 'DAY12', '2026-10-06': 'DAY12', '2026-10-07': 'DAY12' };
    const types = E.analyze(p, '2026-10').conflicts.map(item => item.type);
    assert(types.includes('CONSECUTIVE')); assert(types.includes('OFF_BLOCK'));
});
check('cross-unit duplicates and coverage ranges detected', () => {
    const p = payload(); p.rules.coverage.AM.min = 1; p.rules.coverage.DAY12.max = 0;
    p.assignments[1] = { '2026-10-01': 'DAY12' };
    const a = E.analyze(p, '2026-10', [{ employee_id: 1, date: '2026-10-01', duty: 'DAY12' }]);
    assert.equal(a.unfilled, 31); assert(a.conflicts.some(item => item.type === 'OVERSTAFFED')); assert(a.conflicts.some(item => item.type === 'DUPLICATE'));
});
for (const month of ['2024-02', '2026-02', '2026-04', '2026-10']) {
    check(`auto 12-hour pattern with 2-3 day OFF blocks: ${month}`, () => {
        const p = payload();
        const result = E.generate(p, month, [], solver); const a = E.analyze(result.payload, month);
        assert.equal(a.summaries[0].hours, 168); assert.equal(a.summaries[0].eight, 0); assert.equal(a.summaries[0].twelve, 14);
        assert.deepEqual(a.conflicts.filter(item => !['BOUNDARY'].includes(item.type)), []);
        assert.deepEqual(p.assignments, {}, 'Input is immutable');
    });
    check(`auto 8-hour infeasibility reported without exceeding rest limits: ${month}`, () => {
        const result = E.generate(payload([staff(1, '8')]), month, [], solver); const a = E.analyze(result.payload, month);
        assert(a.summaries[0].hours <= 160); assert(['DEFICIT','COMPLETE'].includes(a.summaries[0].status));
        assert(a.summaries[0].hours >= 144, `Substantial proposal expected: ${a.summaries[0].hours} / ${JSON.stringify(result.notes)}`);
        assert.deepEqual(a.conflicts.filter(item => ['REST', 'CONSECUTIVE', 'OFF_BLOCK', 'OFF'].includes(item.type)), []);
    });
}
check('approved absences, manual entries, grouping, night preference, and boundary rest preserved', () => {
    const p = payload([staff(1), staff(2)]); p.staff[1].preference = 'NIGHT12';
    p.assignments[1] = { '2026-10-10': 'TRAINING' };
    p.requests = [{ employee_id: 1, date: '2026-10-02', type: 'REQUEST_OFF', status: 'APPROVED', remarks: '' }];
    const b = [{ employee_id: 1, date: '2026-09-30', duty: 'NIGHT12' }];
    const result = E.generate(p, '2026-10', b, solver);
    assert.equal(result.payload.assignments[1]['2026-10-02'], 'REQUEST_OFF'); assert.equal(result.payload.assignments[1]['2026-10-10'], 'TRAINING');
    assert(!Object.values(result.payload.assignments[2]).includes('DAY12'));
    assert(!E.analyze(result.payload, '2026-10', b).conflicts.some(item => ['ABSENCE', 'REST', 'CONSECUTIVE', 'OFF_BLOCK'].includes(item.type)));
});
check('an existing mixed-pattern manual assignment cannot make auto exceed 160', () => {
    const p = payload([staff(1, '8')]); p.assignments[1] = { '2026-10-01': 'DAY12' };
    const result = E.generate(p, '2026-10', [], solver); assert(E.summarize(p.staff[0], result.payload.assignments[1]).hours <= 160);
    assert.equal(result.payload.assignments[1]['2026-10-01'], 'DAY12');
});
check('no staffing capacity does not invent duties', () => {
    const p = payload(); Object.values(p.rules.coverage).forEach(rule => rule.max = 0);
    const result = E.generate(p, '2026-10', [], solver); assert.equal(E.summarize(p.staff[0], result.payload.assignments[1]).hours, 0);
});
check('staffing requires separate triage, nurse and attendant counts', () => {
    const employees = Array.from({length: 7}, (_, index) => staff(index + 1, '8'));
    employees[0].staffingRole = 'TRIAGE';
    employees[6].category = 'NA';
    const p = payload(employees);
    p.rules.staffingShifts = ['AM'];
    p.assignments = Object.fromEntries(employees.map(employee => [employee.id, {'2026-10-01': 'AM'}]));
    let a = E.analyze(p, '2026-10');
    assert.deepEqual(a.roleCoverage['2026-10-01'].AM, {TRIAGE: 1, NURSE: 5, NA: 1});
    assert(!a.conflicts.some(item => item.type === 'UNDERSTAFFED' && item.date === '2026-10-01'));
    employees[6].category = 'NURSE';
    a = E.analyze(p, '2026-10');
    assert(a.conflicts.some(item => item.type === 'UNDERSTAFFED' && item.date === '2026-10-01' && item.message.includes('nurse attendant')));
    assert.equal(E.category({position: 'Nursing Attendant'}), 'NA');
    assert.equal(E.staffingRole({category: 'NA', staffingRole: 'TRIAGE'}), 'NA');
    p.rules.staffingTargets = {TRIAGE: 1, NURSE: 6, NA: 0};
    a = E.analyze(p, '2026-10');
    assert(!a.conflicts.some(item => ['UNDERSTAFFED', 'OVERSTAFFED'].includes(item.type) && item.date === '2026-10-01'));
});
for (const month of ['2026-02', '2024-02', '2026-04', '2026-10']) for (const pattern of ['8', '12']) {
    check(`whole-department ${pattern}H target without quotas: ${month}`, () => {
        const p = payload([staff(1, pattern), staff(2, pattern), staff(3, pattern)]);
        Object.assign(p.rules, {maxConsecutive:6, minOff:1, maxOff:31, offPer7:1, staffingShifts:[], staffingTargets:{TRIAGE:0,NURSE:0,NA:0}});
        p.requests.push({employee_id:1, date:month+'-04', type:'REQUEST_OFF', status:'APPROVED', remarks:''});
        p.assignments[1] = {[month+'-04']:'AM'};
        const result = E.generate(p, month, [], solver);
        const analysis = E.analyze(result.payload, month);
        assert.equal(result.payload.assignments[1][month+'-04'], 'REQUEST_OFF');
        for (const row of analysis.summaries) {
            assert.equal(row.hours, pattern === '8' ? 160 : 168, `${row.name}: ${JSON.stringify(result.notes)}`);
            assert.equal(row.eight, pattern === '8' ? 20 : 0);
            assert.equal(row.twelve, pattern === '8' ? 0 : 14);
            assert.equal(Object.keys(result.payload.assignments[row.id]).length, E.dates(month).length);
        }
        assert.deepEqual(analysis.conflicts.filter(c=>c.type!=='BOUNDARY'), []);
    });
}
check('unavailable employee remains included with an honest deficit', () => {
    const p=payload([staff(1,'8'),staff(2,'8')]);
    Object.assign(p.rules,{maxConsecutive:6,minOff:1,maxOff:31,offPer7:1,staffingShifts:[]});
    p.requests=E.dates('2026-10').map(date=>({employee_id:1,date,type:'LEAVE',status:'APPROVED',remarks:''}));
    const result=E.generate(p,'2026-10',[],solver);
    assert.equal(result.payload.staff.length,2);
    assert.equal(E.summarize(p.staff[0],result.payload.assignments[1]).hours,0);
    assert.equal(E.summarize(p.staff[1],result.payload.assignments[2]).hours,160);
    assert(result.notes.some(n=>n.includes('160 hours remain')));
});
console.log(`${checks} scheduling engine checks passed.`);
