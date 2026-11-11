importScripts('../vendor/javascript-lp-solver/solver.js', 'schedule-engine.js?v=13');
self.onmessage = function (event) {
    try {
        const { payload, month, boundaries } = event.data;
        const result = NsSchedule.generate(payload, month, boundaries, solver, (current, total) => self.postMessage({ progress: { current, total } }));
        self.postMessage({ result });
    } catch (error) { self.postMessage({ error: error.message }); }
};
