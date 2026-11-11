(() => {
  'use strict';
  const dialog = document.querySelector('#item-editor-dialog');
  if (!dialog) return;
  const data = JSON.parse(document.querySelector('#item-editor-data').textContent);
  const form = dialog.querySelector('form');
  const fields = [...form.querySelectorAll('.cu-input')];
  const save = form.querySelector('#item-editor-save');
  const auto = form.elements.auto_code;
  const globalError = form.querySelector('#item-editor-error');
  const readiness = form.querySelector('#item-editor-readiness');
  let touched = new Set(), serverErrors = {}, busy = false, manualCode = '';
  function message(field) {
    const value = field.value.trim();
    if (serverErrors[field.name]) return serverErrors[field.name];
    if (field.name === 'item_code' && auto.checked) return '';
    if (field.required && !value) return field.tagName === 'SELECT' ? 'Select ' + ({classification:'a classification',unit:'a unit',storage_location:'a storage location',procurement_type:'a procurement type'}[field.name] || 'an option') + '.' : field.name === 'minimum_stock' ? 'Enter a reorder level.' : 'Enter ' + (field.name === 'item_name' ? 'an item name.' : 'an item code or turn on auto-generate.');
    if (field.name === 'minimum_stock' && (!/^\d+$/.test(value) || Number(value) > 2147483647)) return 'Enter a whole number from 0 to 2,147,483,647.';
    if (field.maxLength > 0 && Array.from(value).length > field.maxLength) return 'Use ' + field.maxLength.toLocaleString() + ' characters or fewer.';
    if (field.name === 'item_code' && data.items.some(item => Number(item.id) !== Number(form.elements.id.value) && String(item.item_code).toUpperCase() === value.toUpperCase())) return 'This item code is already in use. Choose another code.';
    return '';
  }
  function sync() {
    let valid = true;
    fields.forEach(field => {
      const error = message(field); if (error) valid = false;
      const target = document.getElementById('item-error-' + field.name);
      const show = !!error && touched.has(field.name);
      target.textContent = show ? error : ''; target.hidden = !show;
      if (show) field.setAttribute('aria-invalid','true'); else field.removeAttribute('aria-invalid');
    });
    save.disabled = busy || !valid;
    readiness.textContent = busy ? 'Saving item…' : valid ? 'Ready to save.' : 'Complete required fields and correct any errors to enable saving.';
    document.querySelector('#item-description-count').textContent = Array.from(form.elements.item_description.value).length.toLocaleString() + ' / 1,000 characters';
    return valid;
  }
  function syncAuto() {
    const code = form.elements.item_code;
    if (auto.checked) { manualCode = code.value; code.value = ''; }
    else code.value = manualCode;
    code.readOnly = auto.checked; code.required = !auto.checked;
    code.placeholder = auto.checked ? 'Generated when saved' : 'e.g. CSSR-000123';
    document.querySelector('[data-code-required]').hidden = auto.checked;
    document.querySelector('#item-code-help').textContent = auto.checked ? 'A unique code will be assigned when you save.' : 'Use a unique item code, up to 80 characters.';
    delete serverErrors.item_code; sync();
  }
  function openEditor(trigger) {
    form.reset(); touched = new Set(); serverErrors = {}; manualCode = ''; globalError.hidden = true;
    const item = trigger.dataset.itemEditor === 'edit' ? data.items.find(row => Number(row.id) === Number(trigger.dataset.itemId)) : null;
    if (trigger.dataset.itemEditor === 'edit' && !item) return;
    form.elements.id.value = item ? item.id : '0';
    document.querySelector('#item-editor-title').textContent = item ? 'Edit Item' : 'Add New Item';
    auto.checked = !item;
    fields.forEach(field => {
      const key = field.name === 'storage_location' ? 'bin_location' : field.name;
      field.value = item ? (item[key] ?? '') : (field.name === 'minimum_stock' ? '0' : '');
      if (item && field.name === 'unit') {
        const unit = String(item.unit ?? '').trim().toLowerCase();
        field.value = [...field.options].find(option => option.value.toLowerCase() === unit)?.value ?? '';
      }
    });
    manualCode = item?.item_code || ''; syncAuto();
    trigger.focus({preventScroll:true}); dialog.showModal(); form.elements.item_name.focus();
  }
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-item-editor]');
    if (trigger) { event.preventDefault(); openEditor(trigger); }
  });
  document.addEventListener('inventory-results-updated', () => {
    const refreshed = JSON.parse(document.querySelector('#item-editor-data').textContent);
    data.items = refreshed.items;
  });
  fields.forEach(field => {
    field.addEventListener('blur', () => { touched.add(field.name); sync(); });
    for (const event of ['input','change']) field.addEventListener(event, () => { delete serverErrors[field.name]; sync(); });
  });
  auto.addEventListener('change',syncAuto);
  document.querySelectorAll('[data-item-editor-close]').forEach(button => button.addEventListener('click', () => { if (!busy) dialog.close(); }));
  dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
  function setBusy(value) {
    busy = value; window.cssrMotion?.busy(form,value,'Saving item…'); form.setAttribute('aria-busy',String(value));
    form.querySelector('fieldset').disabled = value;
    document.querySelectorAll('[data-item-editor-close]').forEach(button => { button.disabled = value; });
    save.textContent = value ? 'Saving…' : 'Save Item'; sync();
  }
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (busy) return;
    fields.forEach(field => touched.add(field.name));
    if (!sync()) { fields.find(field => message(field))?.focus(); return; }
    const payload = new FormData(form); globalError.hidden = true;
    setBusy(true);
    if (data.preview) {
      setBusy(false); dialog.close(); window.cssrUI.toast('Item saved in the preview. No hospital records were changed.'); return;
    }
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(),20000);
    try {
      const response = await fetch(form.action,{method:'POST',body:payload,credentials:'same-origin',headers:{Accept:'application/json'},signal:controller.signal});
      if (response.redirected) throw Error('Your session may have expired. Refresh the page and sign in again before saving.');
      const isJson = (response.headers.get('content-type') || '').includes('application/json');
      if (!isJson) throw Error(response.status === 403 ? 'The security check failed. Refresh the page before trying again.' : 'The save could not be confirmed. Check inventory before trying again.');
      const result = await response.json();
      if (!response.ok || !result.ok) {
        serverErrors = result.errors || {_form:'Unable to save this item. Please try again.'};
        globalError.textContent = serverErrors._form || 'Review the highlighted fields below.'; globalError.hidden = false;
        setBusy(false); (fields.find(field => message(field)) || globalError).focus(); return;
      }
      setBusy(false); dialog.close(); window.cssrUI.toast(result.message); location.reload();
    } catch (error) {
      globalError.textContent = error.name === 'AbortError' ? 'The save could not be confirmed in time. Check inventory before trying again. Your entries are still here.' : error.message;
      globalError.hidden = false; setBusy(false); globalError.focus();
    } finally { clearTimeout(timeout); }
  });
})();
