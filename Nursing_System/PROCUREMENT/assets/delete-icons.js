(() => {
    const icon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 6h18M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M5 6l1 14a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1L19 6M10 10v7M14 10v7"/></svg>';
    document.querySelectorAll('button').forEach(button => {
        if (button.textContent.trim().toLowerCase() !== 'delete') return;
        const label = button.closest('form')?.dataset.confirm?.split('?')[0] || 'Delete';
        button.classList.add('icon-only');
        button.setAttribute('aria-label', label);
        button.title = label;
        button.dataset.tooltip = 'Delete PPMP';
        button.innerHTML = icon;
    });
})();
