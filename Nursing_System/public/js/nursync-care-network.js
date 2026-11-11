(() => {
    const canvas = document.querySelector('.care-network-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const hero = document.querySelector('#home');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pointer = { x: 0, y: 0 };
    const nodes = [
        { x: .28, y: .15, label: 'Patient care' },
        { x: .79, y: .29, label: 'Resources' },
        { x: .76, y: .76, label: 'Reports' },
        { x: .27, y: .86, label: 'Staff scheduling' },
        { x: .16, y: .48, label: 'Documents' }
    ];
    let width = 0, height = 0, frame = 0, visible = true, last = 0;
    function draw(time = 0) {
        ctx.clearRect(0, 0, width, height);
        const mobile = width < 450;
        const drift = motion.matches || mobile ? 0 : Math.sin(time / 6000) * 5;
        const cx = width * .5, cy = height * .5;
        nodes.forEach((node, index) => {
            const x = node.x * width + pointer.x * 8 + drift;
            const y = node.y * height + pointer.y * 6 + drift * (index % 2 ? -1 : 1);
            const bend = (index % 2 ? 1 : -1) * 35;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.quadraticCurveTo((cx + x) / 2 + bend, (cy + y) / 2 - bend, x, y);
            ctx.strokeStyle = 'rgba(105, 210, 202, .33)';
            ctx.lineWidth = 1;
            ctx.stroke();
            // A small signal travels along each connected workflow.
            const t = motion.matches ? .55 : (time / (mobile ? 10000 : 8000) + index / 5) % 1;
            const qx = (1-t)**2*cx + 2*(1-t)*t*((cx+x)/2+bend) + t*t*x;
            const qy = (1-t)**2*cy + 2*(1-t)*t*((cy+y)/2-bend) + t*t*y;
            ctx.fillStyle = index === 0 ? '#E9B45D' : '#8CE0D8';
            ctx.fillRect(qx - 2, qy - 2, 4, 4);
            ctx.font = `${mobile ? 10 : 12}px Inter, Segoe UI, sans-serif`;
            const boxWidth = ctx.measureText(node.label).width + 30;
            ctx.beginPath();
            ctx.roundRect(x-boxWidth/2, y-17, boxWidth, 34, 5);
            ctx.fillStyle = '#164A60';
            ctx.fill();
            ctx.strokeStyle = 'rgba(140, 224, 216, .4)';
            ctx.stroke();
            ctx.fillStyle = '#EAF7FA';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(node.label, x, y);
        });
    }
    function animate(time) {
        frame = 0;
        if (!visible || document.hidden || motion.matches) return;
        const interval = width < 450 ? 1000 / 20 : 1000 / 30;
        if (time - last >= interval) { draw(time); last = time; }
        frame = requestAnimationFrame(animate);
    }
    function restart() {
        cancelAnimationFrame(frame);
        draw();
        if (visible && !document.hidden && !motion.matches) frame = requestAnimationFrame(animate);
    }
    function resize() {
        const bounds = canvas.getBoundingClientRect();
        width = bounds.width; height = bounds.height;
        const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
        canvas.width = Math.round(width*dpr); canvas.height = Math.round(height*dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        restart();
    }
    hero.addEventListener('pointermove', event => {
        if (motion.matches || event.pointerType !== 'mouse') return;
        const bounds = hero.getBoundingClientRect();
        pointer.x = (event.clientX-bounds.left)/bounds.width-.5;
        pointer.y = (event.clientY-bounds.top)/bounds.height-.5;
    }, { passive: true });
    hero.addEventListener('pointerleave', () => { pointer.x = 0; pointer.y = 0; });
    new ResizeObserver(resize).observe(canvas.parentElement);
    new IntersectionObserver(entries => { visible = entries[0].isIntersecting; restart(); }).observe(hero);
    document.addEventListener('visibilitychange', restart);
    motion.addEventListener('change', restart);
    document.fonts.ready.then(restart);
    resize();
})();
