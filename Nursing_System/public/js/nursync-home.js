/* Additive homepage effects; existing navigation and particle functions are retained. */
(() => {
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const menu = document.getElementById('jvghNavbar');
    const toggle = document.querySelector('.navbar-toggler');
    const closeMenu = () => {
        if (!menu || !window.matchMedia('(max-width: 991px)').matches) return;
        const instance = window.bootstrap?.Collapse.getInstance(menu);
        if (instance) instance.hide();
    };
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu?.classList.contains('show')) {
            closeMenu();
            toggle?.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (menu?.classList.contains('show') && !event.target.closest('.site-navbar')) closeMenu();
    });
    if (motion.matches) return;

    document.querySelectorAll('.home-primary, .home-secondary, .login-pill').forEach((button) => {
        button.addEventListener('pointerdown', (event) => {
            if (motion.matches) return;
            const bounds = button.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'button-ripple';
            ripple.setAttribute('aria-hidden', 'true');
            ripple.style.left = (event.clientX - bounds.left) + 'px';
            ripple.style.top = (event.clientY - bounds.top) + 'px';
            button.append(ripple);
            window.setTimeout(() => ripple.remove(), 550);
        }, { passive: true });
    });
    if (!('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.remove('is-pending');
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: .08 });
    document.querySelectorAll('.section-heading, .purpose-card, .module-card, .team-heading, .team-photo-card, .contact-panel, .overview-section .row, .system-flow').forEach((element) => {
        // Keep already visible content readable immediately, including anchor destinations.
        if (element.getBoundingClientRect().top < window.innerHeight) return;
        element.classList.add('home-reveal', 'is-pending');
        observer.observe(element);
    });
    motion.addEventListener('change', () => {
        if (!motion.matches) return;
        document.querySelectorAll('.is-pending').forEach((element) => element.classList.remove('is-pending'));
        observer.disconnect();
    });
})();
