(() => {
    if (window.nursyncPresentationReady) return;
    window.nursyncPresentationReady = true;
    const presentationUrl = document.currentScript?.src;
    if (presentationUrl) {
        const workflowScript = document.createElement('script');
        workflowScript.src = new URL('workflow-ui.js?v=1', presentationUrl).href;
        workflowScript.defer = true;
        document.head.appendChild(workflowScript);
    }

    function initialize() {
        const menuGrid = document.querySelector('body.menu-page .system-choice-grid');
        if (menuGrid) {
            const destinations = ['/CSSR_INVENTORY/', '/PROCUREMENT/', 'schedule_management.php', 'employee_documents.php', 'dashboard.php', 'reports.php', 'admin_users.php', 'data_backup.php', 'account_settings.php'];
            const rank = card => {
                if (card.getAttribute('aria-disabled') === 'true') return destinations.length + 1;
                const href = card.getAttribute('href') || '';
                const index = destinations.findIndex(destination => href.includes(destination));
                return index < 0 ? destinations.length : index;
            };
            [...menuGrid.children].sort((a, b) => rank(a) - rank(b)).forEach(card => menuGrid.appendChild(card));
        }
        if (presentationUrl && !document.body.classList.contains('cssr-forms')) {
            const background = document.createElement('script');
            background.src = new URL('workspace-background.js?v=1', presentationUrl).href;
            document.head.appendChild(background);
        }
        const navSelector = '.sidebar nav a,.sidebar .menu a,.sidebar-footer a,.tc-sidebar nav a,.proc-sidebar nav a,.system-choice-card';
        const controlSelector = '.sidebar-toggle,.sidebar-collapse,.sidebar-close,.tc-sidebar-close,.ns-document-collapse,.proc-sidebar-collapse,.proc-sidebar-close,.proc-sidebar-toggle,.ed-mobile-toggle,.mobile-menu-toggle,.ns-nursing-close';
        const iconRules = [
            [/logout|sign out/i, 'box-arrow-right'], [/account settings|settings/i, 'sliders2'],
            [/backup|restore/i, 'database-lock'], [/user management|accounts|roles/i, 'people'],
            [/for approval|verification/i, 'patch-check'], [/procurement|purchase|ppmp|\bpr\b/i, 'bag-check'], [/canvass|quotation/i, 'file-earmark-spreadsheet'],
            [/delivery record|newborn|maternal|postpartum|labor monitoring/i, 'heart-pulse'],
            [/deliver|inbound|stock in/i, 'truck'], [/return/i, 'arrow-return-left'],
            [/request|release queue/i, 'clipboard-check'], [/storage|warehouse/i, 'building'],
            [/price/i, 'tags'], [/cssr|inventory|masterlist|stock/i, 'boxes'],
            [/expiry|expiring|expired/i, 'calendar-x'], [/chat|message/i, 'chat-left-text'],
            [/supplier/i, 'truck'], [/department/i, 'diagram-3'], [/attendance|schedule|leave/i, 'calendar-week'],
            [/resigned/i, 'person-dash'], [/upload/i, 'cloud-arrow-up'], [/verif|approval/i, 'patch-check'],
            [/employee|staff|personnel/i, 'person-vcard'], [/document|bin card/i, 'file-earmark-medical'],
            [/audit|history|movement|log/i, 'clock-history'], [/report|analytics|statistics|census/i, 'graph-up-arrow'],
            [/dashboard|overview/i, 'grid-1x2'], [/triage|emergency|\ber\b/i, 'activity'],
            [/discharg|transfer|trans in|trans out/i, 'arrow-left-right'],
            [/patient|admission|admitted/i, 'clipboard2-pulse'], [/bed|ward/i, 'hospital'],
            [/discharg|transfer/i, 'arrow-left-right'], [/consult|doctor|opd/i, 'person-badge'],
            [/nursing|icu|nicu|operating|delivery|lr\s*\/\s*dr/i, 'hospital'], [/menu|workspace/i, 'grid']
        ];
        const painted = new WeakMap();
        const controlPaths = {
            menu: '<rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/>',
            close: '<path d="m6 6 12 12M18 6 6 18"/>',
            collapse: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16m8-12-4 4 4 4"/>',
            expand: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16m4-12 4 4-4 4"/>'
        };
        function paintControl(button) {
            const closing = button.matches('.sidebar-close,.tc-sidebar-close,.proc-sidebar-close,.ns-nursing-close');
            const desktop = button.matches('.sidebar-collapse,.ns-document-collapse,.proc-sidebar-collapse,.nursing-sidebar .sidebar-toggle') || (matchMedia('(min-width: 901px)').matches && button.matches('.proc-sidebar-toggle,[data-sidebar-toggle]'));
            const collapsed = document.body.matches('.sidebar-collapsed,.proc-sidebar-collapsed,.ns-document-collapsed');
            const icon = closing ? 'close' : desktop ? (collapsed ? 'expand' : 'collapse') : 'menu';
            const label = closing ? 'Close navigation' : desktop ? (collapsed ? 'Expand navigation' : 'Collapse navigation') : button.getAttribute('aria-expanded') === 'true' ? 'Close navigation' : 'Open navigation';
            button.setAttribute('aria-label', label); button.title = label;
            if (desktop) button.setAttribute('aria-expanded', String(!collapsed));
            if (button.dataset.nsControl === icon && button.querySelector(':scope>.ns-control-icon')) return;
            button.dataset.nsControl = icon;
            button.innerHTML = '<span class="ns-control-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + controlPaths[icon] + '</svg></span>';
        }
        const tables = new Set();
        const parentWidths = new WeakMap();
        let tableFrame = 0;
        function fitTables() {
            if (tableFrame) return;
            tableFrame = requestAnimationFrame(() => {
                tableFrame = 0;
                tables.forEach(table => {
                    if (!table.isConnected) { tables.delete(table); return; }
                    table.classList.remove('ns-scroll-table');
                    if (!table.getClientRects().length) return;
                    const parent = table.parentElement;
                    const style = getComputedStyle(parent);
                    const available = parent.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
                    // Keep native table layout unless its existing minimum width needs a scroll surface.
                    if (available > 0 && table.getBoundingClientRect().width > available + 1) table.classList.add('ns-scroll-table');
                });
            });
        }
        const tableResize = new ResizeObserver(entries => {
            entries.forEach(entry => {
                if (parentWidths.get(entry.target) === entry.contentRect.width) return;
                parentWidths.set(entry.target, entry.contentRect.width);
                fitTables();
            });
        });
        function paintIcon(host, icon) {
            if (!host || painted.get(host) === icon) return;
            let glyph = host.querySelector(':scope>.ns-nav-glyph');
            if (!glyph) {
                glyph = document.createElement('span');
                glyph.setAttribute('aria-hidden', 'true');
                host.appendChild(glyph);
            }
            host.classList.add('ns-icon-host');
            glyph.className = 'bi bi-' + icon + ' ns-nav-glyph';
            painted.set(host, icon);
        }
        function enhance(root = document) {
            const elements = selector => {
                const matches = [...root.querySelectorAll(selector)];
                if (root instanceof Element && root.matches(selector)) matches.unshift(root);
                return matches;
            };
            elements(navSelector).forEach(link => {
                const label = link.querySelector('.system-choice-copy strong')?.textContent || link.querySelector('em')?.textContent || link.title || link.textContent;
                const icon = iconRules.find(([pattern]) => pattern.test(label.trim()))?.[1] || 'grid';
                const host = link.querySelector('.system-choice-icon,.proc-nav-icon') || link.querySelector(':scope>i,:scope>span:first-child');
                if (host) paintIcon(host, icon);
                if (!link.title) link.title = label.trim();
                if (link.classList.contains('active') && link.getAttribute('href') !== '#') link.setAttribute('aria-current', 'page');
            });
            elements(controlSelector).forEach(button => {
                paintControl(button);
            });
            elements('.login-workspaces span').forEach(item => {
                const icon = iconRules.find(([pattern]) => pattern.test(item.textContent))?.[1];
                if (icon) paintIcon(item.querySelector('i'), icon);
            });
            elements('.confirm-icon,.proc-confirm-icon').forEach(host => {
                if (!painted.has(host)) {
                    [...host.childNodes].filter(node => node.nodeType === Node.TEXT_NODE).forEach(node => { node.textContent = ''; });
                    paintIcon(host, 'question-circle');
                }
            });
            elements('[role="dialog"]').forEach(dialog => {
                // Overlay roots keep their full-screen dimensions; only their inner surfaces are styled.
                if (!dialog.matches('.modal,.app-modal,.confirm-modal,.choice-modal,.discharge-modal,.medical-modal,.ward-modal,.account-confirm-modal,.app-confirm-modal,.admission-assignment-modal,.critical-discharge-modal,.nd-filter-modal,.review-overlay,.request-modal') && !dialog.className.includes('backdrop')) dialog.classList.add('ns-dialog-surface');
            });
            elements('table').forEach(table => {
                if (table.closest('.table-responsive,.table-wrap,.data-table-wrap,.table-container,.item-masterlist-scroll,.pos-receipt,.report-document,.cssr-report-document,.ppmp-page,.print-page,.preview-paper,.report-pdf-mode')) return;
                if (!tables.has(table)) tableResize.observe(table.parentElement);
                tables.add(table);
                fitTables();
            });
        }

        const nursingBrand = document.querySelector('.nursing-sidebar .sidebar-brand');
        if (nursingBrand && !nursingBrand.querySelector('.ns-nursing-close')) {
            const close = document.createElement('button');
            close.type = 'button'; close.className = 'ns-nursing-close';
            close.setAttribute('aria-label', 'Close navigation'); close.title = 'Close navigation';
            close.addEventListener('click', () => document.querySelector('.sidebar-mobile-scrim')?.click());
            nursingBrand.appendChild(close);
        }
        const documentSidebar = document.querySelector('.tc-sidebar');
        if (documentSidebar) {
            const collapse = document.createElement('button');
            collapse.type = 'button'; collapse.className = 'ns-document-collapse';
            function syncCollapse() {
                const collapsed = document.body.classList.contains('ns-document-collapsed');
                collapse.setAttribute('aria-expanded', String(!collapsed));
                collapse.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
                collapse.title = collapse.getAttribute('aria-label');
            }
            try { if (localStorage.getItem('nursyncDocumentSidebarCollapsed') === '1') document.body.classList.add('ns-document-collapsed'); } catch (_) { /* Storage may be disabled. */ }
            collapse.addEventListener('click', () => {
                document.body.classList.toggle('ns-document-collapsed');
                syncCollapse();
                try { localStorage.setItem('nursyncDocumentSidebarCollapsed', document.body.classList.contains('ns-document-collapsed') ? '1' : '0'); } catch (_) { /* The navigation still works without persistence. */ }
            });
            documentSidebar.querySelector('.tc-brand')?.appendChild(collapse);
            syncCollapse();
            // Secondary document screens share the sidebar but do not have the main screen's drawer controls.
            if (!document.querySelector('.ed-mobile-toggle')) {
                const opener = document.createElement('button');
                opener.type = 'button'; opener.className = 'ed-mobile-toggle';
                opener.setAttribute('aria-label', 'Open navigation'); opener.setAttribute('aria-controls', documentSidebar.id);
                opener.addEventListener('click', () => document.body.classList.add('ed-sidebar-open'));
                document.querySelector('.ed-expiring-main .page-head,.ed-main .ed-head')?.prepend(opener);
                document.querySelectorAll('[data-ed-close]').forEach(button => button.addEventListener('click', () => document.body.classList.remove('ed-sidebar-open')));
            }
        }
        enhance();
        const pending = new Set();
        let scheduled = false;
        new MutationObserver(records => {
            records.forEach(record => record.addedNodes.forEach(node => {
                if (node instanceof Element && !node.matches('.ns-nav-glyph,.nursync-ripple')) {
                    pending.add(node);
                    if (node.closest('table')) pending.add(node.closest('table'));
                }
            }));
            if (pending.size && !scheduled) {
                scheduled = true;
                requestAnimationFrame(() => { scheduled = false; pending.forEach(node => { if (node.isConnected) enhance(node); }); pending.clear(); });
            }
        }).observe(document.body, { childList: true, subtree: true });

        const drawers = [
            { state: 'sidebar-mobile-open', sidebar: '.nursing-sidebar', opener: '.mobile-menu-toggle', closer: '.sidebar-mobile-scrim' },
            { state: 'sidebar-open', sidebar: '#appSidebar', opener: '[data-sidebar-toggle]', closer: '[data-sidebar-close]' },
            { state: 'ed-sidebar-open', sidebar: '.tc-sidebar', opener: '.ed-mobile-toggle', closer: '[data-ed-close]' },
            { state: 'proc-nav-open', sidebar: '.proc-sidebar', opener: '.proc-sidebar-toggle', closer: '[data-proc-close]' }
        ];
        const mobile = matchMedia('(max-width: 900px)');
        let currentDrawer = null, previousFocus = null;
        const visible = element => Boolean(element && element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden');
        function syncDrawer() {
            const next = mobile.matches ? drawers.find(drawer => document.body.classList.contains(drawer.state)) : null;
            drawers.forEach(drawer => document.querySelectorAll(drawer.opener).forEach(button => button.setAttribute('aria-expanded', String(next === drawer))));
            document.querySelectorAll(controlSelector).forEach(paintControl);
            if (next === currentDrawer) return;
            if (next) {
                previousFocus = document.activeElement;
                requestAnimationFrame(() => [...document.querySelector(next.sidebar).querySelectorAll('button,a[href]')].find(visible)?.focus({ preventScroll: true }));
            } else if (visible(previousFocus)) previousFocus.focus({ preventScroll: true });
            currentDrawer = next;
        }
        new MutationObserver(syncDrawer).observe(document.body, { attributes: true, attributeFilter: ['class'] });
        mobile.addEventListener('change', () => {
            if (!mobile.matches) drawers.forEach(drawer => document.body.classList.remove(drawer.state));
            syncDrawer();
        });
        syncDrawer();
        document.addEventListener('keydown', event => {
            if (event.defaultPrevented) return;
            if (!currentDrawer || document.querySelector('dialog[open],.modal.show,.app-modal.open,.confirm-modal.open,.app-confirm-modal.open')) return;
            if (event.key === 'Escape') document.querySelector(currentDrawer.closer)?.click();
            if (event.key !== 'Tab') return;
            const focusable = [...document.querySelector(currentDrawer.sidebar).querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),[tabindex="0"]')].filter(visible);
            const first = focusable[0], last = focusable[focusable.length-1];
            if (!first) return;
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
