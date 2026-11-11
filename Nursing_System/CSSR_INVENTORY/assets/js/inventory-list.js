(() => {
  'use strict';
  const notice = document.querySelector('#inventory-success-notice');
  if (notice) window.cssrUI.toast(JSON.parse(notice.textContent));
  // Keep native links and text selection working inside a clickable table row.
  function bindInventoryRows(root = document) { root.querySelectorAll('[data-inventory-row]').forEach(row => {
    row.addEventListener('click', event => {
      if (event.defaultPrevented || event.target.closest('a,button,input,select,textarea,label,summary') || window.getSelection()?.toString()) return;
      const link = row.querySelector('[data-inventory-view]');
      if (link instanceof HTMLAnchorElement && (event.ctrlKey || event.metaKey)) window.open(link.href, '_blank', 'noopener');
      else link?.click();
    });
    row.addEventListener('keydown', event => {
      if (event.target === row && (event.key === 'Enter' || event.key === ' ')) {
        event.preventDefault(); row.querySelector('[data-inventory-view]')?.click();
      }
    });
  });
    const size = root.querySelector('[data-inventory-page-size]');
    size?.addEventListener('change', () => size.form.requestSubmit());
  }
  bindInventoryRows();
  const filters = document.querySelector('#inventory-filters');
  const search = document.querySelector('#inventory-search');
  if (filters && search) {
    let timer;
    let controller;
    let revision = 0;
    let suggestions = [];
    let activeOption = -1;
    let dropdownWanted = false;
    const dropdown = document.createElement('div');
    dropdown.id = 'inventory-search-options';
    dropdown.className = 'cu-inventory-suggestions';
    dropdown.setAttribute('role', 'listbox');
    dropdown.setAttribute('aria-label', 'Matching inventory items');
    dropdown.hidden = true;
    search.parentElement.append(dropdown);
    search.setAttribute('role', 'combobox');
    search.setAttribute('aria-autocomplete', 'list');
    search.setAttribute('aria-controls', dropdown.id);
    search.setAttribute('aria-expanded', 'false');
    search.setAttribute('autocomplete', 'off');
    const closeDropdown = () => {
      dropdown.hidden = true;
      search.setAttribute('aria-expanded', 'false');
      search.removeAttribute('aria-activedescendant');
      activeOption = -1;
    };
    const chooseSuggestion = index => {
      const item = suggestions[index];
      if (!item) return;
      search.value = item.name;
      dropdownWanted = false;
      closeDropdown();
      refresh();
      search.focus();
    };
    const showSuggestions = () => {
      if (!dropdownWanted || !search.value.trim() || document.activeElement !== search) { closeDropdown(); return; }
      dropdown.replaceChildren();
      activeOption = -1;
      search.removeAttribute('aria-activedescendant');
      if (!suggestions.length) {
        const empty = document.createElement('div');
        empty.className = 'cu-inventory-suggestion-empty';
        empty.textContent = 'No matching items in the selected location and filters.';
        dropdown.append(empty);
      }
      suggestions.forEach((item, index) => {
        const option = document.createElement('div');
        option.id = `inventory-search-option-${index}`;
        option.className = 'cu-inventory-suggestion';
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        const name = document.createElement('strong');
        name.textContent = item.name;
        const detail = document.createElement('small');
        detail.textContent = `${item.code} · Count: ${item.stock}`;
        option.append(name, detail);
        option.addEventListener('mousedown', event => event.preventDefault());
        option.addEventListener('click', () => chooseSuggestion(index));
        dropdown.append(option);
      });
      dropdown.hidden = false;
      search.setAttribute('aria-expanded', 'true');
    };
    const feedback = document.createElement('span');
    feedback.className = 'cu-muted';
    feedback.setAttribute('role', 'status');
    feedback.setAttribute('aria-live', 'polite');
    filters.querySelector('.cu-inventory-filter-note')?.append(feedback);
    const refresh = async () => {
      clearTimeout(timer);
      controller?.abort();
      controller = new AbortController();
      const current = ++revision;
      const url = new URL(filters.action, window.location.href);
      url.search = new URLSearchParams(new FormData(filters)).toString();
      url.searchParams.set('page', '1');
      const results = document.querySelector('[aria-labelledby="inventory-list-heading"]');
      results?.setAttribute('aria-busy', 'true');
      feedback.textContent = 'Searching…';
      try {
        const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin' });
        if (!response.ok) throw new Error('Search failed');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        if (current !== revision) return;
        const selectors = ['.cu-inventory-summary', '[aria-labelledby="inventory-list-heading"]'];
        const replacements = selectors.map(selector => [document.querySelector(selector), page.querySelector(selector)]);
        if (replacements.some(([before, after]) => !before || !after)) throw new Error('Search unavailable');
        suggestions = [...page.querySelectorAll('[data-inventory-row]')].map(row => ({
          name: row.querySelector('.cu-item-name')?.textContent.trim() || '',
          code: row.querySelector('.cu-item-code')?.textContent.trim() || '',
          stock: row.querySelector('.cu-stock-value')?.textContent.trim() || '0',
        })).filter(item => item.name);
        replacements.forEach(([before, after]) => before.replaceWith(after));
        showSuggestions();
        const editorData = document.querySelector('#item-editor-data');
        const refreshedEditorData = page.querySelector('#item-editor-data');
        if (editorData && refreshedEditorData) {
          editorData.textContent = refreshedEditorData.textContent;
          document.dispatchEvent(new Event('inventory-results-updated'));
        }
        bindInventoryRows();
        const scope = page.querySelector('.cu-inventory-filter-note > span');
        const scopeLabel = filters.querySelector('.cu-inventory-filter-note > span');
        if (scope && scopeLabel) scopeLabel.textContent = scope.textContent;
        window.history.replaceState(null, '', url);
        const total = document.querySelector('[data-inventory-summary="total"]')?.textContent || '0';
        feedback.textContent = `${total} matching items`;
      } catch (error) {
        if (error.name !== 'AbortError' && current === revision) { closeDropdown(); feedback.textContent = 'Search unavailable. Please try again.'; }
      } finally {
        if (current === revision) document.querySelector('[aria-labelledby="inventory-list-heading"]')?.removeAttribute('aria-busy');
      }
    };
    search.addEventListener('input', event => {
      dropdownWanted = true;
      suggestions = [];
      closeDropdown();
      clearTimeout(timer);
      controller?.abort();
      ++revision;
      if (!event.isComposing) timer = setTimeout(refresh, 250);
    });
    search.addEventListener('compositionend', () => {
      clearTimeout(timer);
      timer = setTimeout(refresh, 250);
    });
    search.addEventListener('focus', () => { dropdownWanted = true; showSuggestions(); });
    search.addEventListener('keydown', event => {
      if (event.key === 'Escape') { dropdownWanted = false; closeDropdown(); return; }
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (!suggestions.length) return;
        event.preventDefault(); dropdownWanted = true;
        if (dropdown.hidden) showSuggestions();
        activeOption = (activeOption + (event.key === 'ArrowDown' ? 1 : -1) + suggestions.length) % suggestions.length;
        [...dropdown.querySelectorAll('[role="option"]')].forEach((option, index) => {
          option.setAttribute('aria-selected', String(index === activeOption));
          if (index === activeOption) { search.setAttribute('aria-activedescendant', option.id); option.scrollIntoView({block:'nearest'}); }
        });
      } else if (event.key === 'Enter' && !dropdown.hidden && activeOption >= 0) {
        event.preventDefault(); chooseSuggestion(activeOption);
      }
    });
    document.addEventListener('click', event => {
      if (!search.parentElement.contains(event.target)) { dropdownWanted = false; closeDropdown(); }
    });
    search.addEventListener('blur', () => { dropdownWanted = false; closeDropdown(); });
    filters.addEventListener('submit', event => { event.preventDefault(); dropdownWanted = false; closeDropdown(); refresh(); });
  }
  // The reference uses one reusable dialog for item actions. No records are changed.
  const source = document.querySelector('#inventory-preview-data');
  if (!source) return;
  const items = JSON.parse(source.textContent);
  const dialog = document.querySelector('#inventory-preview-dialog');
  document.querySelectorAll('[data-preview-item]').forEach(button => button.addEventListener('click', () => {
    const item = items.find(record => Number(record.id) === Number(button.dataset.previewItem));
    if (!item) return;
    const action = button.dataset.previewAction;
    dialog.querySelector('h2').textContent = action === 'history' ? 'Stock History' : 'Item Details';
    dialog.querySelector('[data-preview-item-name]').textContent = item.item_name || 'Unnamed item';
    dialog.querySelector('[data-preview-item-code]').textContent = item.item_code;
    dialog.querySelector('[data-preview-classification]').textContent = item.classification || 'Not specified';
    dialog.querySelector('[data-preview-stock]').textContent = Number(item.current_stock).toLocaleString('en-PH');
    dialog.querySelector('[data-preview-unit]').textContent = item.unit || 'Not specified';
    dialog.querySelector('[data-preview-history]').hidden = action !== 'history';
    dialog.querySelector('[data-history-balance]').textContent = Number(item.current_stock).toLocaleString('en-PH');
    dialog.showModal();
  }));
})();
