(() => {
    const select = document.querySelector('[name="procurement_mode"]');
    const options = [...select.options].filter(option => option.value);
    const wrapper = document.createElement('div');
    wrapper.className = 'ppmp-mode-picker';
    const input = document.createElement('input');
    input.type = 'text';
    input.required = true;
    input.autocomplete = 'off';
    input.placeholder = 'Search procurement mode';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', 'procurementModeOptions');
    const list = document.createElement('div');
    list.id = 'procurementModeOptions';
    list.className = 'ppmp-mode-options';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Procurement modes');
    list.hidden = true;
    wrapper.append(input, list);
    select.after(wrapper);
    select.hidden = true;
    select.required = false;
    let matches = [], active = -1, interactingWithList = false;

    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    }
    function highlight(index) {
        active = index;
        [...list.querySelectorAll('[role="option"]')].forEach((option, i) => {
            option.classList.toggle('active', i === active);
        });
        const option = list.children[active];
        if (option && matches.length) {
            input.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({block: 'nearest'});
        }
    }
    function choose(option) {
        select.value = option.value;
        input.value = option.textContent;
        input.setCustomValidity('');
        select.dispatchEvent(new Event('change', {bubbles:true}));
        close();
    }
    window.setPpmpProcurementMode = value => {
        select.value = value || '';
        input.value = select.selectedOptions[0]?.textContent || '';
        input.setCustomValidity('');
        select.dispatchEvent(new Event('change', {bubbles:true}));
        close();
    };
    function show(filter = '') {
        matches = options.filter(option => option.textContent.toLowerCase().includes(filter.trim().toLowerCase()));
        list.replaceChildren();
        matches.forEach((option, index) => {
            const row = document.createElement('div');
            row.id = 'procurementModeOption' + index;
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', String(option.value === select.value));
            row.textContent = option.textContent;
            row.addEventListener('mousedown', event => event.preventDefault());
            row.addEventListener('click', () => choose(option));
            list.append(row);
        });
        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'ppmp-mode-empty';
            empty.textContent = 'No matching modes';
            list.append(empty);
        }
        active = -1;
        input.removeAttribute('aria-activedescendant');
        list.hidden = false;
        list.scrollTop = 0;
        input.setAttribute('aria-expanded', 'true');
    }
    input.addEventListener('focus', () => show());
    input.addEventListener('click', () => { if (list.hidden) show(); });
    input.addEventListener('input', () => {
        select.value = '';
        input.setCustomValidity(input.value ? 'Choose a procurement mode from the list.' : '');
        select.dispatchEvent(new Event('change', {bubbles:true}));
        show(input.value);
    });
    list.addEventListener('pointerdown', () => { interactingWithList = true; });
    document.addEventListener('pointerup', () => { interactingWithList = false; });
    input.addEventListener('blur', () => {
        // Keep the menu open while its scrollbar or an option is being used.
        setTimeout(() => { if (!interactingWithList && !wrapper.contains(document.activeElement)) close(); }, 0);
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !list.hidden) {
            event.preventDefault(); event.stopPropagation(); close();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) show();
            if (matches.length) highlight((active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length);
        } else if (event.key === 'Enter' && !list.hidden) {
            event.preventDefault();
            if (matches.length) choose(matches[active < 0 ? 0 : active]);
        }
    });
    document.getElementById('ppmpDialog').addEventListener('close', close);
})();
