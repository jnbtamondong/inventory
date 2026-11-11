import * as THREE from '../vendor/three/three.module.min.js';

const host = document.querySelector('.care-network');
if (host) initializeCareScene(host).catch(() => host.classList.remove('is-ready'));

async function initializeCareScene(host) {
    const canvas = host.querySelector('.care-network-canvas');
    const toggle = host.querySelector('.care-scene-toggle');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const compact = matchMedia('(max-width: 991px)');
    const narrow = matchMedia('(max-width: 575px)');
    const context = canvas.getContext('webgl2', { alpha: true, antialias: !compact.matches, powerPreference: 'low-power' });
    if (!context) return;

    const renderer = new THREE.WebGLRenderer({ canvas, context, alpha: true, antialias: !compact.matches });
    renderer.setClearColor(0x082f49, 0);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.3;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(32, 1, .1, 40);
    const assembly = new THREE.Group();
    scene.add(assembly);
    scene.add(new THREE.HemisphereLight(0xe1f8ff, 0x082f49, 2.5));
    const keyLight = new THREE.DirectionalLight(0xffffff, 4);
    keyLight.position.set(-3, 5, 6);
    scene.add(keyLight);
    const rimLight = new THREE.DirectionalLight(0x38bdf8, 3);
    rimLight.position.set(5, -1, 3);
    scene.add(rimLight);
    const tealLight = new THREE.DirectionalLight(0x2cb7a5, 2);
    tealLight.position.set(-5, -3, 1);
    scene.add(tealLight);

    const resources = new Set();
    const track = resource => { resources.add(resource); return resource; };
    const mesh = (geometry, material, parent = assembly) => {
        const object = new THREE.Mesh(track(geometry), track(material));
        parent.add(object);
        return object;
    };
    let frame = 0, lastFrame = 0, elapsed = 0, visible = true, paused = false, lost = false, disposed = false;
    const pointer = new THREE.Vector2();
    const rayPointer = new THREE.Vector2(10, 10);
    const raycaster = new THREE.Raycaster();
    const panels = [], signals = [];

    function release() {
        if (disposed) return;
        disposed = true;
        cancelAnimationFrame(frame);
        resources.forEach(resource => resource.dispose());
        renderer.dispose();
        host.classList.remove('is-ready');
        toggle.hidden = true;
    }

    let seal;
    try {
        seal = track(await new THREE.TextureLoader().loadAsync(host.querySelector('.care-network-seal').src));
    } catch {
        release();
        return;
    }
    seal.colorSpace = THREE.SRGBColorSpace;
    seal.anisotropy = Math.min(renderer.capabilities.getMaxAnisotropy(), 4);

    // The real seal sits on a shallow metal medallion, with an illuminated rim.
    const medallion = new THREE.Group();
    medallion.position.z = .4;
    medallion.rotation.set(.08, -.24, 0);
    assembly.add(medallion);
    const body = mesh(new THREE.CylinderGeometry(.99, .99, .27, 64), new THREE.MeshPhysicalMaterial({ color: 0x237e99, metalness: .65, roughness: .25, clearcoat: .8 }), medallion);
    body.rotation.x = Math.PI / 2;
    const face = mesh(new THREE.CircleGeometry(.89, 64), new THREE.MeshBasicMaterial({ map: seal, color: 0xffffff, transparent: true, toneMapped: false }), medallion);
    face.position.z = .15;
    const rim = mesh(new THREE.TorusGeometry(.95, .04, 8, 64), new THREE.MeshPhysicalMaterial({ color: 0xccebf2, metalness: .6, roughness: .22, clearcoat: 1 }), medallion);
    rim.position.z = .16;
    const lightRim = mesh(new THREE.TorusGeometry(1.04, .012, 6, 64), new THREE.MeshBasicMaterial({ color: 0x38bdf8, transparent: true, opacity: .7 }), medallion);
    lightRim.position.z = -.08;

    await Promise.race([
        Promise.all([document.fonts.load('500 48px Inter'), document.fonts.load('40px bootstrap-icons')]),
        new Promise(resolve => setTimeout(resolve, 1800))
    ]);

    const positions = [
        [-2.2, 1.8, -.2], [2.2, 1.8, -.28], [-2.45, 0, .25],
        [-2.2, -1.8, -.05], [2.2, -1.8, .35], [2.45, 0, .1]
    ];
    const descriptions = ['Nursing workflows', 'Supplies & inventory', 'Employee records', 'Team coordination', 'Insights & monitoring', 'PPMP & purchase requests'];
    const roundedPanel = new THREE.Shape();
    const w = 2.48, h = 1.06, r = .075;
    roundedPanel.moveTo(-w/2+r, -h/2);
    roundedPanel.lineTo(w/2-r, -h/2);
    roundedPanel.quadraticCurveTo(w/2, -h/2, w/2, -h/2+r);
    roundedPanel.lineTo(w/2, h/2-r);
    roundedPanel.quadraticCurveTo(w/2, h/2, w/2-r, h/2);
    roundedPanel.lineTo(-w/2+r, h/2);
    roundedPanel.quadraticCurveTo(-w/2, h/2, -w/2, h/2-r);
    roundedPanel.lineTo(-w/2, -h/2+r);
    roundedPanel.quadraticCurveTo(-w/2, -h/2, -w/2+r, -h/2);
    const panelGeometry = track(new THREE.ExtrudeGeometry(roundedPanel, { depth: .065, bevelEnabled: true, bevelSegments: 2, steps: 1, bevelSize: .018, bevelThickness: .018, curveSegments: 6 }));

    function panelTexture(element, index) {
        const surface = document.createElement('canvas');
        surface.width = 768; surface.height = 328;
        const ctx = surface.getContext('2d');
        const wash = ctx.createLinearGradient(0, 0, 768, 328);
        wash.addColorStop(0, '#235E77'); wash.addColorStop(.55, '#113E58'); wash.addColorStop(1, '#0B314B');
        ctx.fillStyle = wash;
        ctx.fillRect(0, 0, 768, 328);
        ctx.strokeStyle = 'rgba(151, 222, 237, .65)'; ctx.lineWidth = 3;
        ctx.strokeRect(2, 2, 764, 324);
        ctx.fillStyle = '#79DCCD';
        const icon = getComputedStyle(element.querySelector('i'), '::before').content.replace(/^['"]|['"]$/g, '');
        ctx.font = '58px bootstrap-icons';
        ctx.fillText(icon, 43, 100);
        ctx.fillStyle = '#B9D8E5'; ctx.font = '500 29px Inter, Segoe UI, sans-serif';
        ctx.textAlign = 'right'; ctx.fillText(`0${index+1}`, 720, 84);
        ctx.textAlign = 'left'; ctx.fillStyle = '#FFFFFF';
        if (narrow.matches) {
            const words = element.dataset.careModule.split(' ');
            ctx.font = '96px Inter, Segoe UI, sans-serif';
            if (words.length > 1) {
                ctx.fillText(words[0], 43, 195, 687);
                ctx.fillText(words.slice(1).join(' '), 43, 293, 687);
            } else {
                ctx.fillText(words[0], 43, 242, 687);
            }
        } else {
            ctx.font = '52px Inter, Segoe UI, sans-serif';
            ctx.fillText(element.dataset.careModule, 43, 196, 687);
            ctx.font = '400 34px Inter, Segoe UI, sans-serif'; ctx.fillStyle = '#B9D8E5';
            ctx.fillText(descriptions[index], 43, 263);
        }
        const texture = track(new THREE.CanvasTexture(surface));
        texture.colorSpace = THREE.SRGBColorSpace;
        texture.anisotropy = Math.min(renderer.capabilities.getMaxAnisotropy(), 4);
        return texture;
    }

    host.querySelectorAll('[data-care-module]').forEach((element, index) => {
        const group = new THREE.Group();
        group.position.fromArray(positions[index]);
        group.rotation.set(-.04, index % 2 ? -.12 : .12, index === 0 ? .03 : -.025);
        assembly.add(group);
        const material = track(new THREE.MeshPhysicalMaterial({ color: 0x246782, metalness: .35, roughness: .28, clearcoat: 1, emissive: 0x1677a3, emissiveIntensity: .05 }));
        const casing = new THREE.Mesh(panelGeometry, material);
        group.add(casing);
        const label = mesh(new THREE.PlaneGeometry(w-.025, h-.025), new THREE.MeshBasicMaterial({ map: panelTexture(element, index), toneMapped: false }), group);
        label.position.z = .091;
        panels.push({ group, casing, label, element, index });

        const end = group.position.clone();
        const direction = end.clone().setZ(0).normalize();
        const edgeDistance = Math.min((w/2 + .08) / Math.abs(direction.x), (h/2 + .08) / Math.abs(direction.y));
        end.x -= direction.x * edgeDistance;
        end.y -= direction.y * edgeDistance;
        end.z -= .08;
        const start = direction.clone().multiplyScalar(1.02).setZ(.34);
        const curve = new THREE.CubicBezierCurve3(start, start.clone().multiplyScalar(1.35).setZ(.9), end.clone().multiplyScalar(.85).setZ(.8), end);
        mesh(new THREE.TubeGeometry(curve, compact.matches ? 24 : 40, .012, 5, false), new THREE.MeshBasicMaterial({ color: index % 2 ? 0x38bdf8 : 0x2cb7a5, transparent: true, opacity: .65 }));
        const signal = mesh(new THREE.BoxGeometry(.055, .055, .12), new THREE.MeshBasicMaterial({ color: 0xe8b557, toneMapped: false }));
        signals.push({ curve, signal, offset: index / positions.length });
    });

    const dots = new Float32Array(48 * 3);
    for (let i = 0; i < 48; i++) {
        dots[i*3] = (i % 8 - 3.5) * 1.0;
        dots[i*3+1] = (Math.floor(i/8) - 2.5) * .85;
        dots[i*3+2] = -1.1;
    }
    const dotsGeometry = track(new THREE.BufferGeometry());
    dotsGeometry.setAttribute('position', new THREE.BufferAttribute(dots, 3));
    const points = new THREE.Points(dotsGeometry, track(new THREE.PointsMaterial({ color: 0x38bdf8, size: .018, transparent: true, opacity: .28 })));
    assembly.add(points);

    function render(delta = 0) {
        if (disposed || lost) return;
        const moving = !paused && !reducedMotion.matches;
        const drift = moving && !compact.matches ? Math.sin(elapsed * .3) * .012 : 0;
        const blend = delta ? 1 - Math.exp(-delta * 5) : 1;
        assembly.rotation.x += ((moving ? pointer.y * .09 : 0) + .03 - assembly.rotation.x) * blend;
        assembly.rotation.y += ((moving ? pointer.x * .12 : 0) - .08 + drift - assembly.rotation.y) * blend;
        signals.forEach(({ curve, signal, offset }) => {
            const progress = (elapsed * (compact.matches ? .065 : .095) + offset) % 1;
            signal.position.copy(curve.getPoint(progress));
            signal.quaternion.setFromUnitVectors(new THREE.Vector3(0, 0, 1), curve.getTangent(progress).normalize());
        });
        scene.updateMatrixWorld();
        raycaster.setFromCamera(rayPointer, camera);
        const hit = moving && !compact.matches ? raycaster.intersectObjects(panels.map(panel => panel.label), false)[0]?.object : null;
        panels.forEach(panel => {
            const highlight = panel.label === hit;
            panel.casing.material.emissiveIntensity = highlight ? .4 : .05;
            const scale = highlight ? 1.025 : 1;
            panel.group.scale.lerp(new THREE.Vector3(scale, scale, scale), blend);
        });
        renderer.render(scene, camera);
    }

    function animate(now) {
        frame = 0;
        if (!visible || document.hidden || paused || reducedMotion.matches || lost || disposed) return;
        const interval = compact.matches ? 1000/20 : 1000/30;
        if (now - lastFrame >= interval) {
            const delta = Math.min((now-lastFrame)/1000, .1);
            lastFrame = now;
            elapsed += delta;
            render(delta);
        }
        frame = requestAnimationFrame(animate);
    }

    function resume() {
        cancelAnimationFrame(frame);
        frame = 0;
        toggle.hidden = reducedMotion.matches || lost || disposed;
        if (!visible || document.hidden || lost || disposed) return;
        render();
        lastFrame = performance.now();
        if (!paused && !reducedMotion.matches) frame = requestAnimationFrame(animate);
    }

    function resize() {
        if (disposed || lost) return;
        const { width, height } = host.getBoundingClientRect();
        if (!width || !height) return;
        renderer.setPixelRatio(Math.min(devicePixelRatio || 1, compact.matches ? 1.25 : 1.75));
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        const vertical = 5.85;
        const horizontal = 8.15;
        camera.position.set(0, 0, Math.max(vertical/2, horizontal/(2*camera.aspect)) / Math.tan(THREE.MathUtils.degToRad(16)) + .5);
        camera.updateProjectionMatrix();
        points.visible = !compact.matches;
        resume();
    }

    const controller = new AbortController();
    const listenerOptions = { passive: true, signal: controller.signal };
    host.addEventListener('pointermove', event => {
        if (event.pointerType !== 'mouse' || reducedMotion.matches || paused || compact.matches) return;
        const bounds = host.getBoundingClientRect();
        pointer.set((event.clientX-bounds.left)/bounds.width-.5, (event.clientY-bounds.top)/bounds.height-.5);
        rayPointer.set(pointer.x*2, -pointer.y*2);
    }, listenerOptions);
    host.addEventListener('pointerleave', () => { pointer.set(0, 0); rayPointer.set(10, 10); }, listenerOptions);
    toggle.addEventListener('click', () => {
        paused = !paused;
        toggle.setAttribute('aria-pressed', String(paused));
        toggle.setAttribute('aria-label', paused ? 'Resume scene animation' : 'Pause scene animation');
        toggle.title = toggle.getAttribute('aria-label');
        toggle.querySelector('i').className = paused ? 'bi bi-play' : 'bi bi-pause';
        resume();
    }, { signal: controller.signal });
    canvas.addEventListener('webglcontextlost', event => {
        event.preventDefault();
        lost = true;
        host.classList.remove('is-ready');
        resume();
    }, { signal: controller.signal });
    canvas.addEventListener('webglcontextrestored', () => {
        lost = false;
        resize();
        host.classList.add('is-ready');
    }, { signal: controller.signal });
    document.addEventListener('visibilitychange', resume, { signal: controller.signal });
    reducedMotion.addEventListener('change', resume, { signal: controller.signal });
    narrow.addEventListener('change', () => {
        panels.forEach(panel => {
            const old = panel.label.material.map;
            panel.label.material.map = panelTexture(panel.element, panel.index);
            resources.delete(old); old.dispose();
        });
        resize();
    }, { signal: controller.signal });
    const sizeObserver = new ResizeObserver(resize);
    sizeObserver.observe(host);
    const visibilityObserver = new IntersectionObserver(entries => { visible = entries[0].isIntersecting; resume(); });
    visibilityObserver.observe(host);
    window.addEventListener('pagehide', event => {
        cancelAnimationFrame(frame);
        if (!event.persisted) {
            controller.abort(); sizeObserver.disconnect(); visibilityObserver.disconnect(); release();
        }
    }, { signal: controller.signal });
    window.addEventListener('pageshow', resume, { signal: controller.signal });
    resize();
    host.classList.add('is-ready');
}
