const ppmpMasterSearch = document.getElementById('search');
if (ppmpMasterSearch) {
    ppmpMasterSearch.type = 'search';
    ppmpMasterSearch.name = 'ppmp_master_search';
    ppmpMasterSearch.autocomplete = 'off';
    ppmpMasterSearch.setAttribute('aria-label', 'Search PPMP Masterlist');
    ppmpMasterSearch.value = '';
}

document.querySelectorAll('a[href^="ppmp_print.php"]').forEach(link => {
    link.target = '_blank';
    link.rel = 'noopener';
    const actions = link.closest('.actions');
    actions?.classList.add('ppmp-row-actions');
    link.classList.add('icon-only', 'action-preview');
    link.setAttribute('aria-label', 'Preview and print PPMP');
    link.title = 'Preview / Print';
    link.dataset.tooltip = 'Preview / Print';
    link.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    const previewUrl = new URL(link.href, window.location.href);
    const id = previewUrl.searchParams.get('id');
    if (!id || link.parentElement.querySelector('[data-download-ppmp]')) return;
    const row = link.closest('tr');
    const status = row?.querySelector('.badge')?.textContent.trim();
    if (status === 'FOR APPROVAL') {
        link.setAttribute('aria-label', 'Preview PPMP for approval');
        link.title = 'Preview PPMP';
        link.dataset.tooltip = 'Preview PPMP';
    }

    let download = null;
    if (status !== 'FOR APPROVAL') {
        download = document.createElement('a');
        download.className = 'btn green icon-only action-download';
        download.href = `ppmp_download.php?id=${encodeURIComponent(id)}`;
        download.setAttribute('data-download-ppmp', '');
        download.setAttribute('download', '');
        download.setAttribute('aria-label', 'Download PPMP PDF');
        download.title = 'Download PPMP PDF';
        download.dataset.tooltip = 'Download PDF';
        download.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2"/></svg>';
        link.after(download);
    }

    if (row?.querySelector('.badge')?.textContent.trim() === 'DRAFT') {
        const edit = document.createElement('button');
        edit.className = 'btn light icon-only action-edit';
        edit.type = 'button';
        edit.dataset.editPpmp = id;
        edit.dataset.tooltip = 'Edit Draft';
        edit.setAttribute('aria-label', 'Edit draft PPMP');
        edit.title = 'Edit Draft';
        edit.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>';
        (download || link).after(edit);
    }

    actions?.querySelectorAll('form').forEach(form => {
        const action = form.querySelector('input[name="action"]')?.value;
        const button = form.querySelector('button');
        if (!button) return;
        if (action === 'delete') {
            form.classList.add('action-delete');
            return;
        }
        if (action !== 'submit' && action !== 'approve') return;
        form.classList.add('action-workflow');
        button.classList.add('icon-only');
        const isApprove = action === 'approve';
        const label = isApprove ? 'Approve PPMP' : 'Submit PPMP';
        button.setAttribute('aria-label', label);
        button.title = label;
        button.dataset.tooltip = label;
        button.innerHTML = isApprove
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>';
    });
});

const ppmpForm = document.getElementById('ppmpForm');
const ppmpDialogTitle = document.getElementById('ppmpDialogTitle');
const ppmpSaveButton = ppmpForm.querySelector('button[type="submit"]');
ppmpSaveButton.id = 'savePpmpDraft';
ppmpSaveButton.name = 'save_intent';
ppmpSaveButton.value = 'draft';
ppmpSaveButton.formNoValidate = true;
const ppmpSubmitButton = document.createElement('button');
ppmpSubmitButton.className = 'btn gold';
ppmpSubmitButton.type = 'submit';
ppmpSubmitButton.name = 'save_intent';
ppmpSubmitButton.value = 'submit';
ppmpSubmitButton.textContent = 'Submit for Approval';
ppmpForm.dataset.confirmSubmit = 'Submit this PPMP for approval? You cannot edit it again unless it is returned for revision.';
ppmpSaveButton.after(ppmpSubmitButton);
let editorLoadVersion = 0;
function resetPpmpDraftForm() {
    editorLoadVersion++;
    ppmpForm.reset();
    renderReviewComments();
    ppmpDialog.classList.remove('revision-mode');
    const clearCartButton = document.getElementById('clearCart');
    if (clearCartButton) {
        clearCartButton.disabled = false;
        clearCartButton.title = 'Clear Cart';
    }
    ppmpForm.elements.action.value = 'create';
    ppmpForm.elements.id.value = '';
    window.setPpmpProcurementMode?.('');
    lines.splice(0);
    draw();
    ppmpDialogTitle.textContent = 'Create PPMP';
    ppmpSaveButton.textContent = 'Save to Drafts';
    ppmpSubmitButton.textContent = 'Submit for Approval';
    if (new URLSearchParams(location.search).has('revise')) history.replaceState(null, '', 'ppmp_create.php');
}
document.getElementById('toggleForm').addEventListener('click', resetPpmpDraftForm);
ppmpSaveButton.textContent = 'Save to Drafts';
function renderReviewComments(comments = []) {
    window.setPpmpReviewComments?.(comments);
}
function renderGeneralRevisionComment(comment = null) {
    let notice = ppmpForm.querySelector('[data-general-revision-comment]');
    if (!comment) { notice?.remove(); return; }
    if (!notice) { notice = document.createElement('div'); notice.dataset.generalRevisionComment = ''; notice.className = 'ppmp-general-revision-comment'; ppmpForm.querySelector('.ppmp-form-section')?.before(notice); }
    const followUps = (comment.follow_ups || []).map((item, index) => `Follow-up ${index + 1}: ${item.comment}`).join('\n');
    notice.textContent = `GENERAL PPMP COMMENT\n${comment.comment}${followUps ? `\n\n${followUps}` : ''}`;
}
async function openPpmpEditor(id, button = null) {
    const loadVersion = ++editorLoadVersion;
    if (button) button.disabled = true;
    try {
        const response = await fetch(`?edit=${encodeURIComponent(id)}`);
        const draft = await response.json();
        if (loadVersion !== editorLoadVersion) return;
        if (!response.ok) throw new Error(draft.error || 'Unable to load the PPMP.');
        ppmpForm.reset();
        const values = {
            fiscal_year:draft.fiscal_year, fund_source:draft.fund_source, reference_budget:draft.reference_budget, plan_type:draft.plan_type,
            department_id:draft.department_id, project_type:draft.project_type,
            procurement_start_date:draft.procurement_start_date, procurement_end_date:draft.procurement_end_date,
            expected_delivery_date:draft.expected_delivery_date, supporting_documents:draft.supporting_documents,
            remarks:draft.remarks
        };
        Object.entries(values).forEach(([name,value]) => { if (ppmpForm.elements[name]) ppmpForm.elements[name].value = value ?? ''; });
        ppmpForm.elements.action.value = 'update';
        ppmpForm.elements.id.value = draft.id;
        window.setPpmpProcurementMode?.(draft.procurement_mode);
        lines.splice(0);
        draft.items.forEach(item => lines.push({id:Number(item.id),request_id:Number(item.request_id||0),code:item.item_code,description:item.item_name,unit:item.unit||'',qty:Number(item.qty),cost:Number(item.cost),approval_status:item.approval_status||'APPROVED'}));
        draw();
        const isRevision = draft.status === 'FOR REVISION';
        ppmpDialog.classList.toggle('revision-mode', isRevision);
        const clearCartButton = document.getElementById('clearCart');
        if (clearCartButton) {
            clearCartButton.disabled = isRevision;
            clearCartButton.title = isRevision ? 'Clear Cart is disabled during revision' : 'Clear Cart';
        }
        ppmpDialogTitle.textContent = isRevision ? `Revise ${draft.ppmp_no}` : `Edit Draft ${draft.ppmp_no}`;
        renderReviewComments(isRevision ? draft.review_comments : []);
        renderGeneralRevisionComment(isRevision ? draft.general_comment : null);
        ppmpSaveButton.textContent = isRevision ? 'Save Revision' : 'Update Draft';
        ppmpSubmitButton.textContent = isRevision ? 'Resubmit for Approval' : 'Update & Submit';
        ppmpDialog.showModal();
    } catch (error) {
        alert(error.message);
    } finally {
        if (button) button.disabled = false;
    }
}
document.querySelectorAll('[data-edit-ppmp]').forEach(button => button.addEventListener('click', () => openPpmpEditor(button.dataset.editPpmp, button)));
const revisionId = new URLSearchParams(location.search).get('revise');
if (revisionId) openPpmpEditor(revisionId);
else if (location.pathname.endsWith('/ppmp_create.php')) {
    resetPpmpDraftForm();
}
