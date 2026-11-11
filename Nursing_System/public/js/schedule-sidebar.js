(() => {
    document.addEventListener('click', event => {
        const link = event.target.closest('.schedule-sidebar [data-schedule-action]');
        if (!link) return;
        event.preventDefault();
        const action = document.getElementById(link.dataset.scheduleAction);
        if (!action || action.disabled || action.hidden) return;
        document.querySelector('.sidebar-mobile-scrim')?.click();
        action.click();
    });
})();
