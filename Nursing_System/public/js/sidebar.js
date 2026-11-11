document.addEventListener('DOMContentLoaded', function() {
    let submenuCollapseTimer = null;
    const userInfo = document.querySelector('.navbar-menu .user-info');
    const navbarBrand = document.querySelector('.navbar-brand');
    const modalClasses = [
        '.choice-modal',
        '.discharge-modal',
        '.medical-modal',
        '.ward-modal',
        '.confirm-modal',
        '.account-confirm-modal',
        '.app-confirm-modal',
        '.admission-assignment-modal',
        '.critical-discharge-modal',
        '.transfer-modal-backdrop',
        '.nd-filter-modal'
    ];
    const modalSelector = modalClasses.join(', ');
    const openModalSelector = modalClasses.map(function(selector) {
        return selector + '.open';
    }).join(', ');

    function normalizeModals() {
        document.querySelectorAll(modalSelector).forEach(function(modal) {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    }

    function syncModalScrollLock() {
        document.body.classList.toggle('modal-open', document.querySelector(openModalSelector) !== null);
    }

    normalizeModals();
    syncModalScrollLock();

    const modalObserver = new MutationObserver(function(mutations) {
        let shouldNormalize = false;
        let shouldSync = false;

        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                shouldNormalize = true;
                shouldSync = true;
            }

            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                shouldSync = true;
            }
        });

        if (shouldNormalize) {
            normalizeModals();
        }
        if (shouldSync) {
            syncModalScrollLock();
        }
    });

    modalObserver.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class']
    });

    if (navbarBrand && !navbarBrand.querySelector('.navbar-logo-group')) {
        const logoGroup = document.createElement('div');
        logoGroup.className = 'navbar-logo-group';

        [
            ['../public/images/branding/jvgh_seal.png', 'Joni Villanueva General Hospital logo'],
            ['../public/images/branding/doh_logo.png', 'Department of Health logo'],
            ['../public/images/branding/bagong_pilipinas_logo.png', 'Bagong Pilipinas logo']
        ].forEach(function(item) {
            const logo = document.createElement('img');
            logo.className = 'navbar-logo';
            logo.src = item[0];
            logo.alt = item[1];
            logoGroup.appendChild(logo);
        });

        navbarBrand.insertAdjacentElement('afterbegin', logoGroup);
    }

    if (userInfo && !document.querySelector('.navbar-clock')) {
        const clock = document.createElement('span');
        clock.className = 'navbar-clock';
        userInfo.insertAdjacentElement('afterend', clock);

        const updateClock = function() {
            const now = new Date();
            clock.textContent = now.toLocaleString('en-US', {
                month: 'short',
                day: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        };

        updateClock();
        setInterval(updateClock, 1000);
    }

    if (!window.showGlobalConfirm) {
        let modal = document.getElementById('global-confirm-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'global-confirm-modal';
            modal.className = 'app-confirm-modal';
            modal.setAttribute('aria-hidden', 'true');
            modal.innerHTML = `
                <div class="app-confirm-card" role="dialog" aria-modal="true">
                    <h3 data-confirm-title>Confirm Action</h3>
                    <p data-confirm-message>Are you sure?</p>
                    <div class="app-confirm-actions">
                        <button type="button" class="btn btn-secondary" data-confirm-cancel>Cancel</button>
                        <button type="button" class="btn btn-primary" data-confirm-ok>Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        const title = modal.querySelector('[data-confirm-title]');
        const message = modal.querySelector('[data-confirm-message]');
        const confirmButton = modal.querySelector('[data-confirm-ok]');
        let pendingConfirmAction = null;

        const closeGlobalConfirm = function() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            modal.style.display = '';
            pendingConfirmAction = null;
        };

        window.showGlobalConfirm = function(options) {
            const settings = options || {};
            const viewportTop = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
            const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
            title.textContent = settings.title || 'Confirm Action';
            message.textContent = settings.message || 'Are you sure?';
            confirmButton.textContent = settings.confirmText || 'Confirm';
            confirmButton.className = 'btn ' + (settings.confirmClass || 'btn-primary');
            pendingConfirmAction = typeof settings.onConfirm === 'function' ? settings.onConfirm : null;
            modal.style.setProperty('position', 'absolute', 'important');
            modal.style.setProperty('top', viewportTop + 'px', 'important');
            modal.style.setProperty('left', '0', 'important');
            modal.style.setProperty('right', 'auto', 'important');
            modal.style.setProperty('bottom', 'auto', 'important');
            modal.style.setProperty('width', '100vw', 'important');
            modal.style.setProperty('height', viewportHeight + 'px', 'important');
            modal.style.setProperty('min-height', viewportHeight + 'px', 'important');
            modal.style.setProperty('display', 'block', 'important');
            modal.style.setProperty('padding', '20px', 'important');
            modal.style.setProperty('overflow', 'hidden', 'important');
            modal.style.setProperty('z-index', '5000', 'important');
            var card = modal.querySelector('.app-confirm-card');
            if (card) {
                card.style.setProperty('position', 'absolute', 'important');
                card.style.setProperty('top', '50%', 'important');
                card.style.setProperty('left', '50%', 'important');
                card.style.setProperty('right', 'auto', 'important');
                card.style.setProperty('margin', '0', 'important');
                card.style.setProperty('transform', 'translate(-50%, -50%)', 'important');
                card.style.setProperty('z-index', '5001', 'important');
            }
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        };

        modal.querySelector('[data-confirm-cancel]').addEventListener('click', closeGlobalConfirm);
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeGlobalConfirm();
            }
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.classList.contains('open')) {
                closeGlobalConfirm();
            }
        });
        confirmButton.addEventListener('click', function() {
            const action = pendingConfirmAction;
            closeGlobalConfirm();
            if (action) {
                action();
            }
        });
    }

    const logoutLinks = document.querySelectorAll('a[href="../api/logout.php"]');
    logoutLinks.forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            const logoutUrl = link.getAttribute('href') || '../api/logout.php';
            window.showGlobalConfirm({
                title: 'Confirm Logout',
                message: 'Are you sure you want to logout?',
                confirmText: 'Logout',
                confirmClass: 'btn-danger',
                onConfirm: function() {
                    window.location.href = logoutUrl;
                }
            });
        });
    });

    function initMobileDrawer() {
        const sidebar = document.querySelector('.sidebar');
        const navbar = document.querySelector('.navbar');
        if (!sidebar || !navbar || document.querySelector('.mobile-menu-toggle')) {
            return;
        }

        const menuButton = document.createElement('button');
        menuButton.type = 'button';
        menuButton.className = 'mobile-menu-toggle';
        menuButton.setAttribute('aria-label', 'Open navigation menu');
        menuButton.setAttribute('aria-controls', sidebar.id || 'nursync-sidebar');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
        if (!sidebar.id) {
            sidebar.id = 'nursync-sidebar';
        }

        const scrim = document.createElement('button');
        scrim.type = 'button';
        scrim.className = 'sidebar-mobile-scrim';
        scrim.setAttribute('aria-label', 'Close navigation menu');

        navbar.insertAdjacentElement('afterbegin', menuButton);
        document.body.appendChild(scrim);

        function setDrawer(open) {
            document.body.classList.toggle('sidebar-mobile-open', open);
            menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        menuButton.addEventListener('click', function() {
            setDrawer(!document.body.classList.contains('sidebar-mobile-open'));
        });
        scrim.addEventListener('click', function() {
            setDrawer(false);
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                setDrawer(false);
            }
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 900) {
                setDrawer(false);
            }
        });
    }

    function initButtonRipples() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        document.addEventListener('click', function(event) {
            const button = event.target.closest('.module-ui .btn, .module-ui button[type="submit"]');
            if (!button || button.disabled) {
                return;
            }
            const rect = button.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'nursync-ripple';
            ripple.style.left = (event.clientX - rect.left) + 'px';
            ripple.style.top = (event.clientY - rect.top) + 'px';
            button.appendChild(ripple);
            window.setTimeout(function() {
                ripple.remove();
            }, 520);
        });
    }

    function initScrollReveal() {
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const revealTargets = document.querySelectorAll('.module-ui :is(.stat-card,.metric-card,.dashboard-card,.form-section,.page-panel,.panel,.nd-card,.report-card)');
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });

        revealTargets.forEach(function(target) {
            target.classList.add('nursync-reveal');
            observer.observe(target);
        });
    }

    function initParticleBackground() {
        const isModulePage = document.body.classList.contains('module-ui');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const smallScreen = window.matchMedia('(max-width: 700px)').matches;
        if (!isModulePage || reducedMotion || smallScreen || document.querySelector('.nursync-particle-canvas')) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.className = 'nursync-particle-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        document.body.insertAdjacentElement('afterbegin', canvas);

        const context = canvas.getContext('2d');
        const pointer = { x: -9999, y: -9999 };
        let particles = [];
        let animationFrame = 0;

        function resizeCanvas() {
            const ratio = Math.min(window.devicePixelRatio || 1, 2);
            canvas.width = Math.floor(window.innerWidth * ratio);
            canvas.height = Math.floor(window.innerHeight * ratio);
            canvas.style.width = window.innerWidth + 'px';
            canvas.style.height = window.innerHeight + 'px';
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            const count = Math.min(46, Math.max(24, Math.floor(window.innerWidth / 34)));
            particles = Array.from({ length: count }, function() {
                return {
                    x: Math.random() * window.innerWidth,
                    y: Math.random() * window.innerHeight,
                    vx: (Math.random() - 0.5) * 0.22,
                    vy: (Math.random() - 0.5) * 0.22,
                    r: 1.2 + Math.random() * 1.7
                };
            });
        }

        function draw() {
            context.clearRect(0, 0, window.innerWidth, window.innerHeight);
            context.lineWidth = 1;

            particles.forEach(function(particle, index) {
                const dxPointer = particle.x - pointer.x;
                const dyPointer = particle.y - pointer.y;
                const pointerDistance = Math.sqrt(dxPointer * dxPointer + dyPointer * dyPointer);
                if (pointerDistance < 120) {
                    particle.vx += dxPointer / 26000;
                    particle.vy += dyPointer / 26000;
                }

                particle.x += particle.vx;
                particle.y += particle.vy;

                if (particle.x < -20 || particle.x > window.innerWidth + 20) {
                    particle.vx *= -1;
                }
                if (particle.y < -20 || particle.y > window.innerHeight + 20) {
                    particle.vy *= -1;
                }

                context.beginPath();
                context.arc(particle.x, particle.y, particle.r, 0, Math.PI * 2);
                context.fillStyle = 'rgba(50, 140, 193, 0.36)';
                context.fill();

                for (let i = index + 1; i < particles.length; i += 1) {
                    const other = particles[i];
                    const dx = particle.x - other.x;
                    const dy = particle.y - other.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    if (distance < 115) {
                        context.beginPath();
                        context.moveTo(particle.x, particle.y);
                        context.lineTo(other.x, other.y);
                        context.strokeStyle = 'rgba(93, 169, 233, ' + (0.13 * (1 - distance / 115)) + ')';
                        context.stroke();
                    }
                }
            });

            animationFrame = requestAnimationFrame(draw);
        }

        resizeCanvas();
        draw();
        window.addEventListener('resize', resizeCanvas);
        window.addEventListener('mousemove', function(event) {
            pointer.x = event.clientX;
            pointer.y = event.clientY;
        });
        window.addEventListener('mouseleave', function() {
            pointer.x = -9999;
            pointer.y = -9999;
        });
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                cancelAnimationFrame(animationFrame);
            } else {
                draw();
            }
        });
    }

    initMobileDrawer();
    initButtonRipples();
    initScrollReveal();
    initParticleBackground();

    requestAnimationFrame(function() {
        document.body.classList.add('sidebar-ready');
    });

    if (document.querySelector('.dynamic-sidebar')) {
        document.body.classList.add('has-dynamic-sidebar');
        document.body.classList.remove('sidebar-collapsed');
        try { localStorage.removeItem('nursingSidebarCollapsed'); } catch (_) { /* Navigation works without storage. */ }
        return;
    }

    const isScheduleSidebar = Boolean(document.querySelector('.schedule-sidebar'));
    // The old preference was also written on every navigation click. Start the
    // clinical menu expanded; persist only an explicit press of its toggle.
    const sidebarStateKey = isScheduleSidebar ? 'nursingScheduleSidebarCollapsed' : 'nursingSidebarCollapsedV2';

    function closeOpenSubmenus() {
        document.querySelectorAll('.sidebar .has-submenu.open').forEach(function(item) {
            item.classList.remove('open');
        });
    }

    let collapsedState = null;
    try { collapsedState = localStorage.getItem(sidebarStateKey); } catch (_) { /* Use the expanded sidebar. */ }
    if (collapsedState === '1') {
        document.body.classList.add('sidebar-collapsed');
        closeOpenSubmenus();
    }

    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem(sidebarStateKey, document.body.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch (_) { /* Navigation works without storage. */ }
        });
    }

    document.querySelectorAll('.has-submenu > a[href="#"]').forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            const parentItem = this.parentElement;
            const willOpen = !parentItem.classList.contains('open');
            const siblingItems = parentItem.parentElement.querySelectorAll(':scope > .has-submenu.open');
            siblingItems.forEach(function(item) {
                if (item !== parentItem) {
                    item.classList.remove('open');
                }
            });
            parentItem.classList.toggle('open', willOpen);
            clearTimeout(submenuCollapseTimer);
        });
    });

    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.addEventListener('mouseenter', function() {
            clearTimeout(submenuCollapseTimer);
        });
    }

    document.querySelectorAll('.sidebar a[href]:not([href="#"])').forEach(function(link) {
        link.addEventListener('click', function() {
            document.body.classList.remove('sidebar-mobile-open');
            closeOpenSubmenus();
            link.blur();
        });
    });
});
