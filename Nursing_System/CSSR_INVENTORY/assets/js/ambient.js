(() => {
    if (!document.body.classList.contains('cssr-forms')) return;
    const canvas = document.createElement('canvas');
    canvas.className = 'cssr-ambient';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.prepend(canvas);
    const context = canvas.getContext('2d');
    if (!context) { canvas.remove(); return; }
    let width = 0;
    let height = 0;
    const phase = 0;

    function paint() {
        context.clearRect(0, 0, width, height);
        // Open contours echo the reference without obscuring the work surface.
        for (let line = 0; line < 7; line++) {
            context.beginPath();
            context.lineWidth = 1;
            context.strokeStyle = line === 3 ? 'rgba(44,183,165,0.11)' : 'rgba(22,119,163,0.065)';
            const start = height * (line / 6);
            context.moveTo(0, start);
            context.bezierCurveTo(
                width * 0.28, start + Math.sin(phase + line * 0.6) * 180,
                width * 0.72, start + Math.cos(phase * 0.8 + line * 0.6) * 180,
                width, start + Math.sin(phase + line) * 90
            );
            context.stroke();
        }
    }
    function resize() {
        width = window.innerWidth;
        height = window.innerHeight;
        const ratio = Math.min(window.devicePixelRatio || 1, 1.5);
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        paint();
    }
    window.addEventListener('resize', resize, { passive: true });
    // Keep the background still; interaction feedback supplies the motion.
    window.addEventListener('pageshow', resize);
    resize();
})();
