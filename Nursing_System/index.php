<?php
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$navItems = [
    'index.php' => 'Home',
    'dashboard_preview.php' => 'Dashboard',
    '#about' => 'Mission & Vision',

    '#services' => 'Modules',

    '#team' => 'Leadership',
    '#contact' => 'Contact'
];

function navActive($page, $currentPage) {
    if (substr($page, 0, 1) === '#') {
        return '';
    }

    return $page === $currentPage ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NURSYNC | Joni Villanueva General Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/vendor/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="public/css/nursync-home.css?v=7">
    <link rel="stylesheet" href="public/css/workspace-ui.css?v=1">
</head>
<body class="story-site">
    <nav class="navbar navbar-expand-lg sticky-top site-navbar">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php" aria-label="NURSYNC Homepage">
                <img class="brand-logo home-brand-seal" src="public/images/branding/jvgh_seal.png" alt="JVGH seal">
                <span class="brand-text">
                    <strong>NURSYNC</strong>
                    <span>JVGH Nursing Management System</span>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#jvghNavbar" aria-controls="jvghNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="jvghNavbar">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <?php foreach ($navItems as $page => $label): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo navActive($page, $currentPage); ?>" href="<?php echo htmlspecialchars($page); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="login-pill ms-lg-3" href="pages/login.php">Login</a>
            </div>
        </div>
    </nav>

    <main>
        <section class="hero" id="home">
            <?php include __DIR__ . '/includes/public_background.php'; ?>
            <span class="home-grid-overlay" aria-hidden="true"></span>
            <span class="home-vignette-overlay" aria-hidden="true"></span>
            <div class="container hero-content">
                <div class="hero-copy">
                <div class="hero-hospital-brand">
                    <img class="hero-seal" src="public/images/branding/jvgh_seal.png" alt="Joni Villanueva General Hospital seal" width="56" height="56" fetchpriority="high">
                    <span class="eyebrow">JONI VILLANUEVA GENERAL HOSPITAL</span>
                </div>
                <p class="home-product-name">Nursing Management System</p>
                <h1>NURSYNC</h1>
                <p class="hero-tagline">Connecting Nurses. <span>Coordinating Care.</span></p>
                <p class="hero-subtitle">The digital Nursing Management System of Joni Villanueva General Hospital, designed to connect nursing operations, staff management, resources, documentation, and reporting in one system.</p>
                <div class="home-hero-actions">
                    <a class="home-primary" href="pages/login.php"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Enter NURSYNC <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    <a class="home-secondary" href="#services">Explore NURSYNC <i class="bi bi-chevron-down" aria-hidden="true"></i></a>
                </div>
                <div class="hero-system-summary" aria-label="NURSYNC at a glance">
                    <div><strong>6</strong><span>CONNECTED MODULES</span></div>
                    <div><strong>JVGH</strong><span>NURSING SERVICE</span></div>
                    <div><strong>One System</strong><span>CONNECTED OPERATIONS</span></div>
                </div>
                <div class="home-hero-note"><span aria-hidden="true"></span>One nursing system. Everything connected.</div>
                </div>
                <div class="care-network">
                    <div class="hero-watermark-logo" aria-hidden="true"><img src="public/images/branding/jvgh_seal.png" alt=""></div>
                    <canvas class="care-network-canvas" aria-hidden="true"></canvas>
                    <div class="care-network-fallback" aria-hidden="true">
                        <span class="care-fallback-line"></span><span class="care-fallback-line"></span><span class="care-fallback-line"></span><span class="care-fallback-line"></span><span class="care-fallback-line"></span><span class="care-fallback-line"></span>
                        <img class="care-network-seal" src="public/images/branding/jvgh_seal.png" alt="" width="108" height="108">
                        <span class="care-fallback-node" data-care-module="Patient Care"><i class="bi bi-heart-pulse"></i>Patient Care</span>
                        <span class="care-fallback-node" data-care-module="Resources"><i class="bi bi-box-seam"></i>Resources</span>
                        <span class="care-fallback-node" data-care-module="Documents"><i class="bi bi-file-earmark-text"></i>Documents</span>
                        <span class="care-fallback-node" data-care-module="Staff Scheduling"><i class="bi bi-calendar3"></i>Staff Scheduling</span>
                        <span class="care-fallback-node" data-care-module="Reports"><i class="bi bi-bar-chart-line"></i>Reports</span>
                        <span class="care-fallback-node" data-care-module="Procurement"><i class="bi bi-cart-check"></i>Procurement</span>
                    </div>
                    <button class="care-scene-toggle" type="button" aria-label="Pause scene animation" aria-pressed="false" title="Pause scene animation" hidden><i class="bi bi-pause" aria-hidden="true"></i></button>
                </div>
            </div>
        </section>

        <section class="section-block about-section" id="about">
            <?php include __DIR__ . '/includes/public_background.php'; ?>
            <div class="container">
                <div class="section-heading"><span class="home-section-label">OUR SHARED PURPOSE</span><h2>Mission &amp; Vision</h2></div>
                <div class="row g-3 g-md-4">
                    <div class="col-md-6">
                        <article class="purpose-card">
                            <span class="module-icon" aria-hidden="true"><i class="bi bi-heart-pulse"></i></span>
                            <h3>MISSION</h3>
                            <p>The Nursing Service Department of Joni Villanueva General Hospital is committed to leading the provision of holistic and quality patient-centered nursing care for the people of Bulacan.</p>
                        </article>
                    </div>
                    <div class="col-md-6">
                        <article class="purpose-card">
                            <span class="module-icon" aria-hidden="true"><i class="bi bi-eye"></i></span>
                            <h3>VISION</h3>
                            <p>The constituents of the province of Bulacan shall be among the healthiest in the nation through the provision of excellent, holistic, and integrative care by the Nursing Service of Joni Villanueva General Hospital.</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-block overview-section" id="overview">
            <div class="container">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-6"><span class="home-section-label">THE NURSYNC ENVIRONMENT</span><h2>One System.<br>Connected Nursing Operations.</h2></div>
                    <div class="col-lg-6">
                        <p class="overview-copy">NURSYNC connects nursing workflows, staff management, resources, documentation, monitoring, and reporting in one digital environment. It brings day-to-day operations into a shared workspace, helping nursing teams coordinate care and maintain organized records.</p>
                        <div class="overview-pillars"><span><i class="bi bi-people" aria-hidden="true"></i> People</span><span><i class="bi bi-heart-pulse" aria-hidden="true"></i> Care</span><span><i class="bi bi-box-seam" aria-hidden="true"></i> Resources</span><span><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Records</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-block modules-section" id="services">
            <?php include __DIR__ . '/includes/public_background.php'; ?>
            <div class="container">
                <div class="section-heading"><span class="home-section-label">NURSYNC MODULES</span><h2>The right workspace for every workflow.</h2><p class="section-introduction">Connected tools for patient care, nursing teams, and hospital operations.</p></div>
                <div class="module-grid">
                    <article class="module-card"><span class="module-icon" aria-hidden="true"><i class="bi bi-clipboard2-pulse"></i></span><h3>Patient Care Management</h3><p>Coordinate patient records, care status, and movement.</p></article>
                    <article class="module-card"><span class="module-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span><h3>CSSR Inventory</h3><p>Manage supplies, inventory records, and resource tracking.</p></article>
                    <article class="module-card"><span class="module-icon" aria-hidden="true"><i class="bi bi-cart-check"></i></span><h3>Procurement</h3><p>Manage PPMP, purchase requests, and pending deliveries.</p></article>
                    <article class="module-card"><span class="module-icon" aria-hidden="true"><i class="bi bi-file-earmark-person"></i></span><h3>Nursing Employee Documents</h3><p>Keep nursing staff documents organized and accessible.</p></article>
                    <article class="module-card"><span class="module-icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span><h3>Staff Scheduling</h3><p>Coordinate staff assignments and nursing schedules.</p></article>
                    <article class="module-card"><span class="module-icon" aria-hidden="true"><i class="bi bi-bar-chart-line"></i></span><h3>Reports and Analytics</h3><p>Review nursing activity, operational reports, and trends.</p></article>
                </div>
            </div>
        </section>

        <section class="section-block team-section" id="team">
            <?php include __DIR__ . '/includes/public_background.php'; ?>
            <div class="container">
                <div class="team-heading">
                    <span class="team-eyebrow">LEADERSHIP</span>
                    <h2>The people guiding our nursing service.</h2>
                    <p class="section-introduction">Working together to guide, support, and strengthen nursing care at JVGH.</p>
                </div>
                <div class="row g-4 justify-content-center">
                    <div class="col-sm-6 col-lg-4 col-xl">
                        <div class="team-photo-card">
                            <div class="team-initials" aria-hidden="true">RT</div>
                            <div class="team-caption">
                                <span>Medical Center Chief I</span>
                                <strong>Dr. Renely P. Tungol</strong>
                                <em>MD, MPM, MHA, CSEE</em>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-4 col-xl">
                        <div class="team-photo-card">
                            <div class="team-initials" aria-hidden="true">JD</div>
                            <div class="team-caption">
                                <span>Chief Nurse</span>
                                <strong>Jimson R. De Jesus</strong>
                                <em>RN, MAN, PhD, DipHLM, CHA</em>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-4 col-xl">
                        <div class="team-photo-card">
                            <div class="team-initials" aria-hidden="true">RD</div>
                            <div class="team-caption">
                                <span>Nurse Supervisor</span>
                                <strong>Rachel Joy H. Divina</strong>
                                <em>RN</em>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-4 col-xl">
                        <div class="team-photo-card">
                            <div class="team-initials" aria-hidden="true">RM</div>
                            <div class="team-caption">
                                <span>Nurse Supervisor</span>
                                <strong>Ron Martin Mendoza</strong>
                                <em>RN, MAN</em>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-4 col-xl">
                        <div class="team-photo-card">
                            <div class="team-initials" aria-hidden="true">LC</div>
                            <div class="team-caption">
                                <span>Nurse Supervisor</span>
                                <strong>Lawrenz Ruiz Centeno</strong>
                                <em>RN</em>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-block contact-section" id="contact">
            <?php include __DIR__ . '/includes/public_background.php'; ?>
            <div class="container">
                <div class="contact-panel">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-5">
                            <div class="contact-heading">
                                <span>Contact Us</span>
                                <h2>JVGH Nursing Service</h2>
                                <p>For inquiries and coordination, you may use the official location and email listed here.</p>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="contact-info">
                                <div class="contact-item">
                                    <span class="contact-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11z"></path>
                                            <circle cx="12" cy="10" r="2.5"></circle>
                                        </svg>
                                    </span>
                                    <div>
                                        <strong>Location</strong>
                                        <span>National Road, Igulot, Bocaue, Bulacan, Philippines</span>
                                    </div>
                                </div>
                                <div class="contact-item">
                                    <span class="contact-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M4 6h16v12H4z"></path>
                                            <path d="m4 7 8 6 8-6"></path>
                                        </svg>
                                    </span>
                                    <div>
                                        <strong>Email</strong>
                                        <a href="mailto:nsosecjvgh@gmail.com">nsosecjvgh@gmail.com</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                <div>
                    <strong>NURSYNC</strong>
                    <div>Joni Villanueva General Hospital</div>
                </div>
                <div>One nursing system. Everything connected.</div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const navbar = document.querySelector('.site-navbar');
        const setNavbarState = () => {
            navbar.classList.toggle('is-scrolled', window.scrollY > 8);
        };

        setNavbarState();
        window.addEventListener('scroll', setNavbarState, { passive: true });

        document.querySelectorAll('.navbar-collapse .nav-link, .navbar-collapse .login-pill').forEach((link) => {
            link.addEventListener('click', () => {
                const menu = document.querySelector('#jvghNavbar');
                const instance = bootstrap.Collapse.getInstance(menu);
                if (instance && window.matchMedia('(max-width: 991px)').matches) {
                    instance.hide();
                }
            });
        });

        (() => {
            const canvas = document.getElementById('home-particle-canvas');
            if (!canvas || !canvas.getContext) return;
            const ctx = canvas.getContext('2d');
            const hero = document.getElementById('home');
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            let width = 0, height = 0, ratio = 1, particles = [], ripples = [], animationFrame = 0;
            const pointer = { x: -1000, y: -1000 };

            const resize = () => {
                const bounds = hero.getBoundingClientRect();
                width = Math.max(1, bounds.width);
                height = Math.max(1, bounds.height);
                ratio = Math.min(window.devicePixelRatio || 1, 2);
                canvas.width = Math.round(width * ratio);
                canvas.height = Math.round(height * ratio);
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                const count = Math.max(28, Math.min(82, Math.round(width * height / 15000)));
                particles = Array.from({ length: count }, () => ({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    vx: (Math.random() - .5) * .32,
                    vy: (Math.random() - .5) * .32,
                    radius: .8 + Math.random() * 1.8
                }));
            };

            const draw = () => {
                ctx.clearRect(0, 0, width, height);
                for (let index = ripples.length - 1; index >= 0; index--) {
                    const ripple = ripples[index];
                    ripple.radius += 3.2;
                    const opacity = Math.max(0, (1 - ripple.radius / ripple.max) * .62);
                    ctx.beginPath(); ctx.arc(ripple.x, ripple.y, ripple.radius, 0, Math.PI * 2);
                    ctx.strokeStyle = `rgba(50,140,193,${opacity * .72})`; ctx.lineWidth = 1.3; ctx.stroke();
                    if (ripple.radius >= ripple.max) ripples.splice(index, 1);
                }
                particles.forEach((particle, index) => {
                    if (!reduceMotion) {
                        particle.x += particle.vx;
                        particle.y += particle.vy;
                        if (particle.x < 0 || particle.x > width) particle.vx *= -1;
                        if (particle.y < 0 || particle.y > height) particle.vy *= -1;
                        const px = particle.x - pointer.x, py = particle.y - pointer.y;
                        const pointerDistance = Math.hypot(px, py);
                        if (pointerDistance > 0 && pointerDistance < 130) {
                            const force = (130 - pointerDistance) / 130;
                            particle.x += (px / pointerDistance) * force * 1.2;
                            particle.y += (py / pointerDistance) * force * 1.2;
                        }
                    }
                    ctx.beginPath();
                    ctx.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(50,140,193,.52)';
                    ctx.fill();
                    for (let next = index + 1; next < particles.length; next++) {
                        const other = particles[next];
                        const dx = particle.x - other.x;
                        const dy = particle.y - other.y;
                        const distance = Math.hypot(dx, dy);
                        if (distance < 130) {
                            ctx.beginPath();
                            ctx.moveTo(particle.x, particle.y);
                            ctx.lineTo(other.x, other.y);
                            ctx.strokeStyle = `rgba(11,60,93,${(1 - distance / 130) * .14})`;
                            ctx.lineWidth = .75;
                            ctx.stroke();
                        }
                    }
                });
                if (!reduceMotion) animationFrame = requestAnimationFrame(draw);
            };

            resize();
            draw();
            hero.addEventListener('pointermove', event => {
                const bounds = hero.getBoundingClientRect();
                pointer.x = event.clientX - bounds.left;
                pointer.y = event.clientY - bounds.top;
            }, { passive: true });
            hero.addEventListener('pointerleave', () => { pointer.x = -1000; pointer.y = -1000; }, { passive: true });
            hero.addEventListener('pointerdown', event => {
                if (reduceMotion) return;
                const bounds = hero.getBoundingClientRect();
                ripples.push({ x:event.clientX-bounds.left, y:event.clientY-bounds.top, radius:5, max:110 });
            }, { passive: true });
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    cancelAnimationFrame(animationFrame);
                    resize();
                    draw();
                }, 120);
            }, { passive: true });
        })();
    </script>
    <script src="public/js/nursync-home.js?v=1"></script>
    <script src="public/js/workspace-background.js?v=1"></script>
</body>
</html>
