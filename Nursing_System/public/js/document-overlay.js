document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    const preview = document.querySelector('.document-preview-overlay');
    if (preview) {
        event.preventDefault();
        preview.remove();
        return;
    }

    const closeLink = document.querySelector(
        '.review-head .head-actions > a:first-child, .popup > .head > a:first-of-type'
    );
    if (!closeLink?.href) return;

    event.preventDefault();
    window.location.href = closeLink.href;
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form input[name="issue_date"]').forEach((issueInput) => {
        const type = issueInput.form?.querySelector('[name="document_type"]')?.value;
        if (type === 'PDS' || type === 'IPCR') {
            issueInput.value = '';
            issueInput.disabled = true;
            const label = issueInput.closest('label');
            if (label) label.hidden = true;
        }
        if (type === 'IPCR') {
            const range = document.createElement('div');
            range.className = 'ipcr-date-range wide';
            range.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:11px';
            range.innerHTML = '<label>IPCR Start Date<input type="date" name="ipcr_start_date" required></label><label>IPCR End Date<input type="date" name="ipcr_end_date" required></label>';
            const fields = issueInput.closest('.fields') || issueInput.form;
            const remarksLabel = issueInput.form?.querySelector('textarea[name="notes"]')?.closest('label');
            fields.insertBefore(range, remarksLabel || null);
        }
    });
    document.querySelectorAll('form input[name="document_file"]').forEach((fileInput) => {
        const form = fileInput.closest('form');
        if (!form || form.querySelector('.filename-instruction')) return;
        const instruction = document.createElement('div');
        instruction.className = 'filename-instruction wide';
        instruction.style.cssText = 'padding:12px 14px;border:1px solid #b9d7e8;border-radius:9px;background:#eef8fd;color:#174d6b;font-size:12px;line-height:1.55';
        instruction.innerHTML = '<span>Filename format: </span><strong>LASTNAME_FIRSTNAME_DOCUMENT.pdf</strong> <span>Example: </span><strong>SANTOS_MARIA_TRAINING.pdf</strong>';
        const fields = fileInput.closest('.fields');
        (fields || form).prepend(instruction);
    });
    document.querySelectorAll('.review-table a[href*="download="]').forEach((link) => {
        const row = link.closest('tr');
        if (!row) return;

        link.removeAttribute('target');
        link.innerHTML = '<i class="fa-solid fa-file-circle-check"></i> View &amp; Action';
        link.addEventListener('click', (event) => {
            event.preventDefault();
            document.querySelector('.document-preview-overlay')?.remove();

            const overlay = document.createElement('div');
            overlay.className = 'document-preview-overlay';
            const panel = document.createElement('section');
            panel.className = 'document-preview-panel';
            const title = row.querySelector('td strong')?.textContent?.trim() || 'Employee Document';
            panel.innerHTML = `
                <header class="document-preview-head">
                    <div><small>DOCUMENT PREVIEW</small><strong></strong></div>
                    <button type="button" aria-label="Close preview">&times;</button>
                </header>
                <iframe title="PDF preview"></iframe>
                <footer class="document-preview-actions"></footer>`;
            panel.querySelector('.document-preview-head strong').textContent = title;
            panel.querySelector('iframe').src = link.href;

            const actions = panel.querySelector('.document-preview-actions');
            row.querySelectorAll('.decision-form').forEach((form) => actions.appendChild(form.cloneNode(true)));
            if (!actions.children.length) actions.innerHTML = '<span class="status">No action is available for your own upload.</span>';
            const close = () => overlay.remove();
            panel.querySelector('.document-preview-head button').addEventListener('click', close);
            overlay.addEventListener('click', (clickEvent) => {
                if (clickEvent.target === overlay) close();
            });
            overlay.appendChild(panel);
            document.body.appendChild(overlay);
        });
    });
});
