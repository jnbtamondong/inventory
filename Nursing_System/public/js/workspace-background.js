(() => {
    if (document.querySelector('.ns-workspace-background,.cssr-ambient') || document.body.classList.contains('report-pdf-mode')) return;
    const canvas = document.createElement('canvas');
    canvas.className = 'ns-workspace-background';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.prepend(canvas);
    const context = canvas.getContext('2d');
    if (!context) { canvas.remove(); return; }
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    let width = 0, height = 0, frame = 0, lastPaint = 0, phase = 0;
    function paint() {
        context.clearRect(0, 0, width, height);
        // Open contours provide motion without competing with labels or records.
        const publicPage = document.body.matches('.story-site,.nursync-login,.menu-page');
        for (let line = 0; line < 9; line++) {
            const start = height * (line / 8);
            context.beginPath();
            context.lineWidth = 1;
            context.strokeStyle = line % 3 === 0 ? `rgba(44,183,165,${publicPage ? .23 : .10})` : `rgba(22,119,163,${publicPage ? .14 : .06})`;
            context.moveTo(0, start);
            context.bezierCurveTo(width*.28, start+Math.sin(phase+line*.6)*180, width*.72, start+Math.cos(phase*.8+line*.6)*180, width, start+Math.sin(phase+line)*90);
            context.stroke();
        }
    }
    function tick(time) {
        if (time-lastPaint >= 50) { phase += Math.min(time-lastPaint,100)*.00012; lastPaint=time; paint(); }
        frame=requestAnimationFrame(tick);
    }
    function sync() {
        cancelAnimationFrame(frame); frame=0; lastPaint=performance.now(); paint();
        if (!motion.matches && !document.hidden) frame=requestAnimationFrame(tick);
    }
    function resize() {
        width=innerWidth; height=innerHeight;
        const ratio=Math.min(devicePixelRatio || 1,1.5);
        canvas.width=Math.round(width*ratio); canvas.height=Math.round(height*ratio);
        context.setTransform(ratio,0,0,ratio,0,0); paint();
    }
    addEventListener('resize',resize,{passive:true});
    document.addEventListener('visibilitychange',sync);
    motion.addEventListener('change',sync);
    addEventListener('pagehide',()=>cancelAnimationFrame(frame));
    addEventListener('pageshow',sync);
    resize(); sync();
})();
