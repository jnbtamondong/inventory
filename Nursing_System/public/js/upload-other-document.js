document.addEventListener('DOMContentLoaded', () => {
    const type = document.querySelector('[data-type]');
    const grid = type?.closest('.form-grid');
    if (!type || !grid) return;
    grid.classList.add('upload-compact-grid');

    const field = document.createElement('label');
    field.dataset.otherTrainingField = '';
    field.hidden = true;
    field.innerHTML = 'Specify Document Name *<input type="text" name="document_title" maxlength="180" placeholder="Enter the specific document or training name">';
    type.closest('label')?.after(field);
    const input = field.querySelector('input');

    function syncOtherTraining() {
        const show = type.value === 'Other Trainings' || type.value === 'Others';
        field.hidden = !show;
        input.required = show;
        if (!show) input.value = '';
    }

    type.addEventListener('change', syncOtherTraining);
    document.querySelector('[data-category]')?.addEventListener('change', () => setTimeout(syncOtherTraining));
    syncOtherTraining();
});
