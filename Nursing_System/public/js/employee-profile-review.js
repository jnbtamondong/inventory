document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const syncIssueDateFields = () => {
        document.querySelectorAll('form input[name="issue_date"]').forEach((issueInput) => {
            const form = issueInput.closest('form');
            const typeField = form?.querySelector('[name="document_type"]');
            const isIpcr = typeField?.value === 'IPCR';
            const hideIssueDate = typeField?.value === 'PDS' || isIpcr;
            const label = issueInput.closest('label');
            if (label) label.hidden = hideIssueDate;
            issueInput.disabled = hideIssueDate;
            if (hideIssueDate) issueInput.value = '';
            let range = form.querySelector('.ipcr-date-range');
            if (!range) {
                range = document.createElement('div');
                range.className = 'ipcr-date-range';
                range.style.cssText = 'display:contents';
                range.innerHTML = '<label>IPCR Start Date<input type="date" name="ipcr_start_date"></label><label>IPCR End Date<input type="date" name="ipcr_end_date"></label>';
                const fieldGrid = issueInput.closest('.form-grid') || form;
                const remarksLabel = form.querySelector('textarea[name="notes"]')?.closest('label');
                fieldGrid.insertBefore(range, remarksLabel || null);
            }
            range.hidden = !isIpcr;
            range.querySelectorAll('input').forEach((input) => {
                input.disabled = !isIpcr;
                input.required = isIpcr;
                if (!isIpcr) input.value = '';
            });
        });
    };
    document.addEventListener('change', (event) => {
        if (event.target.matches('[name="document_type"]')) syncIssueDateFields();
    });
    document.addEventListener('click', () => setTimeout(syncIssueDateFields, 0));
    syncIssueDateFields();
    document.querySelectorAll('form input[name="document_file"]').forEach((fileInput) => {
        const form = fileInput.closest('form');
        if (!form || form.querySelector('.filename-instruction')) return;
        const instruction = document.createElement('div');
        instruction.className = 'filename-instruction';
        instruction.style.cssText = 'grid-column:1/-1;margin:0 0 12px;padding:12px 14px;border:1px solid #b9d7e8;border-radius:9px;background:#eef8fd;color:#174d6b;font-size:12px;line-height:1.55';
        instruction.innerHTML = '<span>Filename format: </span><strong>LASTNAME_FIRSTNAME_DOCUMENT.pdf</strong> <span>Example: </span><strong>SANTOS_MARIA_TRAINING.pdf</strong>';
        const grid = fileInput.closest('.form-grid');
        (grid || form).prepend(instruction);
    });
    const filenameCodes = {'Proof of Appointment':'PROOFOFAPPOINTMENT','PDS':'PDS','IPCR':'IPCR','Diploma':'DIPLOMA','PRC License':'PRC','Civil Service Eligibility':'CIVILSERVICEELIGIBILITY','PNA':'PNA','BLS':'BLS','ACLS':'ACLS','ER Training':'ERTRAINING','OR Training':'ORTRAINING','DR Training':'DRTRAINING','NICU Training':'NICUTRAINING','ICU Training':'ICUTRAINING','Other Trainings':'TRAINING'};
    const filenameToken = (value) => String(value || '').replace(/[^a-z0-9]/gi, '').toUpperCase();
    const syncRequiredFilenames = () => {
        document.querySelectorAll('form input[name="document_file"]').forEach((fileInput) => {
            const form = fileInput.closest('form');
            const output = form?.querySelector('[data-required-filename]');
            if (!output) return;
            const employeeSelect = form.querySelector('select[name="employee_id"]');
            const employeeInput = form.querySelector('input[name="employee_id"]')?.closest('label')?.querySelector('input:not([type="hidden"])');
            const employeeName = (employeeSelect?.selectedOptions[0]?.textContent || employeeInput?.value || '').split('—')[0].trim();
            const nameParts = employeeName.split(/\s+/).filter(Boolean);
            const firstName = nameParts[0] || '';
            const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
            const type = form.querySelector('[name="document_type"]')?.value || '';
            const otherTitle = form.querySelector('[name="document_title"]')?.value || '';
            const code = type === 'Others' ? filenameToken(otherTitle) : (filenameCodes[type] || filenameToken(type));
            output.textContent = firstName && lastName && code ? `${filenameToken(lastName)}_${filenameToken(firstName)}_${code}.pdf` : 'LASTNAME_FIRSTNAME_DOCUMENTTYPE.pdf';
        });
    };
    document.addEventListener('input', syncRequiredFilenames);
    document.addEventListener('change', syncRequiredFilenames);
    document.addEventListener('click', () => setTimeout(syncRequiredFilenames, 0));
    syncRequiredFilenames();
    if (params.get('view') === 'employees') {
        document.querySelectorAll('[data-search-body] tr').forEach((row) => {
            const required = Number(row.cells[4]?.textContent.trim() || 0);
            const compliance = row.cells[9];
            if (required === 0 && compliance) {
                compliance.innerHTML = '<strong>N/A</strong><small style="display:block;margin-top:4px;color:#738795">No required documents</small>';
            }
        });
    }
    const verificationShortcut = [...document.querySelectorAll('.head-actions button')]
        .find((button) => button.textContent.includes('For Verification'));
    if (verificationShortcut) {
        verificationShortcut.removeAttribute('onclick');
        verificationShortcut.addEventListener('click', () => {
            window.location.href = 'employee_documents.php?view=documents&filter=pending';
        });
    }

    if (params.get('view') === 'documents') {
        const isQueue = params.get('filter') === 'pending';
        document.querySelectorAll('[data-open-employee-docs]').forEach((link) => {
            const employeeId = link.dataset.openEmployeeDocs;
            link.href = isQueue
                ? `employee_documents.php?view=documents&filter=pending&employee_id=${encodeURIComponent(employeeId)}`
                : `employee_documents.php?view=profile&employee_id=${encodeURIComponent(employeeId)}`;
            link.textContent = isQueue ? 'Review Documents' : 'View Documents';
        });
    }

    if (params.get('view') === 'upload' && params.get('renew')) {
        const uploadForm = document.querySelector('form[enctype="multipart/form-data"]');
        if (uploadForm) {
            uploadForm.querySelector('[name="action"]').value = 'replace';
            const parentId = document.createElement('input');
            parentId.type = 'hidden';
            parentId.name = 'parent_document_id';
            parentId.value = params.get('renew');
            uploadForm.append(parentId);
            uploadForm.querySelector('[name="return_view"]').value = 'profile';
            const returnEmployee = document.createElement('input');
            returnEmployee.type = 'hidden';
            returnEmployee.name = 'return_employee_id';
            returnEmployee.value = params.get('employee_id') || '';
            uploadForm.append(returnEmployee);

            const category = uploadForm.querySelector('[name="category"]');
            const type = uploadForm.querySelector('[name="document_type"]');
            if (category && params.get('category')) {
                category.value = params.get('category');
                category.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (type && params.get('document_type')) {
                type.value = params.get('document_type');
                type.dispatchEvent(new Event('change', { bubbles: true }));
            }

            const heading = uploadForm.closest('.panel')?.querySelector('h2');
            if (heading) heading.textContent = 'Renew Employee Document';
            const submit = uploadForm.querySelector('button[type="submit"], button:not([type])');
            if (submit) submit.textContent = 'Upload Renewal for Verification';
        }
    }

    if (params.get('view') === 'employees') {
        const toolbar = [...document.querySelectorAll('.toolbar')]
            .find((element) => element.querySelector('h2')?.textContent.trim() === 'Employee Master List');
        const search = toolbar?.querySelector('[data-search]');
        if (toolbar && search) {
            const controls = document.createElement('div');
            controls.className = 'employee-master-filters';
            controls.style.cssText = 'display:grid;grid-template-columns:220px 155px 180px;align-items:center;gap:8px;max-width:100%';
            const filter = document.createElement('select');
            filter.className = 'search';
            filter.setAttribute('aria-label', 'Filter by staff category');
            filter.innerHTML = '<option value="">All Staff</option><option value="nurse">Nurse</option><option value="nursing_attendant">Nursing Attendant</option><option value="midwife">Midwife</option><option value="clerk">Clerk</option>';
            filter.value = params.get('staff_group') || '';
            filter.addEventListener('change', () => {
                const url = new URL(window.location.href);
                if (filter.value) url.searchParams.set('staff_group', filter.value);
                else url.searchParams.delete('staff_group');
                window.location.href = url.toString();
            });
            const departmentFilter = document.createElement('select');
            departmentFilter.className = 'search';
            departmentFilter.setAttribute('aria-label', 'Filter by department');
            const rows = [...document.querySelectorAll('[data-search-body] [data-row]')];
            departmentFilter.innerHTML = '<option value="">All Departments</option>';
            fetch('employee_documents.php?department_options=1')
                .then((response) => response.ok ? response.json() : Promise.reject(new Error('Unable to load departments.')))
                .then((departments) => {
                    departments.forEach(({ label }) => departmentFilter.add(new Option(label, label)));
                })
                .catch(() => {
                    [...new Set(rows.map((row) => row.cells[3]?.textContent.trim()).filter(Boolean))]
                        .sort((a, b) => a.localeCompare(b))
                        .forEach((department) => departmentFilter.add(new Option(department, department)));
                });
            const applyEmployeeFilters = () => {
                const query = search.value.trim().toLowerCase();
                const department = departmentFilter.value;
                rows.forEach((row) => {
                    const matchesSearch = !query || row.textContent.toLowerCase().includes(query);
                    const matchesDepartment = !department || row.cells[3]?.textContent.trim() === department;
                    row.style.display = matchesSearch && matchesDepartment ? '' : 'none';
                });
            };
            search.addEventListener('input', applyEmployeeFilters);
            departmentFilter.addEventListener('change', applyEmployeeFilters);
            search.remove();
            search.style.cssText = 'width:220px!important;min-width:0!important';
            filter.style.cssText = 'width:155px!important;min-width:0!important';
            departmentFilter.style.cssText = 'width:180px!important;min-width:0!important';
            controls.append(search, filter, departmentFilter);
            toolbar.append(controls);

            const compactFilters = window.matchMedia('(max-width: 720px)');
            const syncFilterLayout = () => {
                controls.style.gridTemplateColumns = compactFilters.matches ? '1fr' : '220px 155px 180px';
                [search, filter, departmentFilter].forEach((control) => {
                    control.style.width = compactFilters.matches ? '100%' : control === search ? '220px' : control === filter ? '155px' : '180px';
                });
            };
            compactFilters.addEventListener?.('change', syncFilterLayout);
            syncFilterLayout();
        }

        document.querySelectorAll('[data-search-body] [data-row] a[href*="view=upload"]').forEach((link) => link.remove());
    }

    if (params.get('view') !== 'profile') return;

    const employeeId = params.get('employee_id');
    if (employeeId) {
        fetch(`employee_documents.php?leave_summary=${encodeURIComponent(employeeId)}`)
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then((result) => {
                if (!result.success) return;
                const s = result.summary;
                const values = [['Late Count',s.late_count],['Late Minutes',s.late_minutes_total],['Absent',s.absent_count],['VL Used',s.vacation_leave_used],['SL Used',s.sick_leave_used],['SPL Used',s.special_privilege_leave_used],['Wellness Used',s.wellness_leave_used],['Calamity Used',s.calamity_leave_used]];
                const section = document.createElement('section');
                section.className = 'panel employee-leave-summary';
                section.innerHTML = `<div class="leave-summary-head"><h2>Attendance &amp; Leave Summary</h2><small>${result.year}</small></div><div class="leave-summary-grid">${values.map(([label,value])=>`<article><strong>${Math.round(Number(value||0))}</strong><small>${label}</small></article>`).join('')}</div>`;
                document.getElementById('overview')?.after(section);
            }).catch(() => {});
    }
    const expectedDocuments = {
        'Employment Documents': ['Proof of Appointment', 'PDS', 'IPCR'],
        'Professional Documents': ['Diploma', 'PRC License', 'Civil Service Eligibility', 'PNA'],
        'Training & Certificates': ['BLS', 'ACLS', 'ER Training', 'OR Training', 'DR Training', 'NICU Training', 'ICU Training', 'Other Trainings']
    };
    Object.entries(expectedDocuments).forEach(([category, types]) => {
        const section = [...document.querySelectorAll('.panel[id]')].find((panel) => panel.querySelector('h2')?.textContent.trim() === category);
        const body = section?.querySelector('tbody');
        if (!body) return;
        const uploaded = new Set([...body.rows].map((row) => row.cells[0]?.textContent.trim().toLowerCase()));
        types.forEach((type) => {
            if (uploaded.has(type.toLowerCase())) return;
            const row = body.insertRow();
            row.className = 'missing-document-row';
            row.innerHTML = `<td>${type}</td><td>—</td><td><span class="badge not-uploaded">NOT UPLOADED</span></td><td>—</td>`;
        });
    });
    if (employeeId && document.querySelector('[data-can-mark-resigned="1"]')) {
        const profileHead = document.querySelector('.profile-head');
        const profileName = profileHead?.querySelector('h2')?.textContent.trim() || 'this employee';
        const profilePanel = profileHead?.closest('.panel');
        const profileTabs = profilePanel?.querySelector('.tabs');
        if (profilePanel && profileTabs) {
            const profileControls = document.createElement('div');
            profileControls.className = 'profile-management-actions';
            const resignedButton = document.createElement('button');
            resignedButton.type = 'button';
            resignedButton.className = 'btn red';
            resignedButton.style.marginTop = '10px';
            resignedButton.innerHTML = '<i class="fa-solid fa-user-slash"></i> Mark as Resigned';
            resignedButton.addEventListener('click', () => {
                let dialog = document.querySelector('.resigned-confirm-dialog');
                if (!dialog) {
                    dialog = document.createElement('dialog');
                    dialog.className = 'resigned-confirm-dialog';
                    dialog.innerHTML = `<form method="post">
                        <header><div><small>EMPLOYEE STATUS</small><strong>Mark as Resigned</strong></div><button type="button" data-resigned-close aria-label="Close">&times;</button></header>
                        <div class="resigned-confirm-body"><i class="fa-solid fa-user-slash"></i><h3></h3><p>The employee account will be disabled. All uploaded documents, verification records, and audit history will be retained.</p></div>
                        <footer><button class="btn light" type="button" data-resigned-close>Cancel</button><button class="btn red" type="submit"><i class="fa-solid fa-user-slash"></i> Confirm Resignation</button></footer>
                        <input type="hidden" name="csrf_token"><input type="hidden" name="action" value="mark_resigned"><input type="hidden" name="employee_id"><input type="hidden" name="return_view" value="employees">
                    </form>`;
                    document.body.append(dialog);
                    dialog.querySelectorAll('[data-resigned-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
                    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
                }
                dialog.querySelector('form').action = 'employee_documents.php?view=profile&employee_id=' + encodeURIComponent(employeeId);
                dialog.querySelector('h3').textContent = `Mark ${profileName} as resigned?`;
                dialog.querySelector('[name="csrf_token"]').value = document.querySelector('meta[name="csrf-token"]')?.content || '';
                dialog.querySelector('[name="employee_id"]').value = employeeId;
                dialog.showModal();
            });
            profileControls.append(resignedButton);
            profileTabs.after(profileControls);
        }
    }
    const reasonDialog = document.createElement('dialog');
    reasonDialog.className = 'document-reason-dialog';
    reasonDialog.innerHTML = `
        <div class="reason-dialog-head">
            <div><small>DOCUMENT REVIEW</small><strong>Rejection Details</strong></div>
            <button type="button" aria-label="Close">×</button>
        </div>
        <div class="reason-dialog-body">
            <label>Document<input type="text" data-reason-document readonly></label>
            <label>Status<input type="text" value="REJECTED" readonly></label>
            <label class="reason-wide">Rejection Reason<textarea data-reason-text rows="5" readonly></textarea></label>
        </div>
        <div class="reason-dialog-footer"><button type="button" class="btn light">Close</button></div>`;
    document.body.append(reasonDialog);

    const reasonStyle = document.createElement('style');
    reasonStyle.textContent = `.document-reason-dialog{position:fixed;inset:0;width:min(620px,94vw);max-height:min(560px,90vh);margin:auto;padding:0;border:0;border-radius:14px;box-shadow:0 24px 70px rgba(15,34,52,.35);color:#173b52}.document-reason-dialog::backdrop{background:rgba(25,43,58,.58)}.reason-dialog-head{display:flex;align-items:center;justify-content:space-between;padding:14px 17px;border-bottom:1px solid #dce8ef}.reason-dialog-head small,.reason-dialog-head strong{display:block}.reason-dialog-head small{color:#2381b2;font-size:10px;font-weight:900;letter-spacing:.08em}.reason-dialog-head strong{margin-top:3px;color:#0b3855;font-size:17px}.reason-dialog-head>button{border:1px solid #d2e0e8;border-radius:8px;background:#fff;color:#173f59;width:34px;height:34px;font-size:20px;cursor:pointer}.reason-dialog-body{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:17px}.reason-dialog-body label{color:#496578;font-size:11px;font-weight:800}.reason-dialog-body input,.reason-dialog-body textarea{display:block;width:100%;margin-top:5px;padding:10px;border:1px solid #cbdce5;border-radius:8px;background:#f7fafb;color:#173b52;resize:none}.reason-wide{grid-column:1/-1}.reason-dialog-footer{display:flex;justify-content:flex-end;padding:11px 17px;border-top:1px solid #dce8ef;background:#fafcfd}@media(max-width:520px){.reason-dialog-body{grid-template-columns:1fr}.reason-wide{grid-column:auto}}`;
    document.head.append(reasonStyle);
    reasonDialog.querySelector('.reason-dialog-head>button').addEventListener('click', () => reasonDialog.close());
    reasonDialog.querySelector('.reason-dialog-footer button').addEventListener('click', () => reasonDialog.close());
    reasonDialog.addEventListener('click', (event) => {
        if (event.target === reasonDialog) reasonDialog.close();
    });

    const makeButton = (label) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn light';
        button.textContent = label;
        return button;
    };
    const openRenewalPopup = (documentId, category, type, mode = 'renew') => {
        const modal = document.getElementById('replaceModal');
        if (!modal) return;

        document.getElementById('replaceId').value = documentId;
        document.getElementById('replaceEmployee').value = employeeId || '';
        document.getElementById('replaceCategory').value = category;
        document.getElementById('replaceType').value = type;
        const expiryField = modal.querySelector('[data-replace-expiry]');
        const expiryInput = expiryField?.querySelector('input');
        const requiresExpiry = ['PRC License', 'BLS', 'ACLS', 'PNA'].includes(type);
        if (expiryField) expiryField.hidden = !requiresExpiry;
        if (expiryInput) {
            expiryInput.required = requiresExpiry;
            if (!requiresExpiry) expiryInput.value = '';
        }
        modal.classList.add('renewal-upload-modal');
        modal.style.width = 'min(900px, 94vw)';
        if (!document.getElementById('renewalUploadStyle')) {
            const style = document.createElement('style');
            style.id = 'renewalUploadStyle';
            style.textContent = `#replaceModal.renewal-upload-modal{position:fixed;inset:auto!important;top:20px!important;left:50%!important;transform:translateX(-50%)!important;width:min(980px,96vw)!important;max-height:calc(100vh - 40px)!important;margin:0!important;padding:0;border:1px solid #d8e5ec;border-radius:13px;box-shadow:0 24px 70px rgba(15,34,52,.32);overflow:auto!important}#replaceModal.renewal-upload-modal::backdrop{background:rgba(25,43,58,.30)}#replaceModal.renewal-upload-modal .modal-head{position:sticky;top:0;z-index:2;display:flex;align-items:flex-start;justify-content:space-between;padding:15px 18px 13px;border-bottom:1px solid #e1e9ee;background:#fff;color:#0b3855}#replaceModal.renewal-upload-modal .renewal-eyebrow{display:block;margin-bottom:4px;color:#2381b2;font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}#replaceModal.renewal-upload-modal .modal-head strong{display:block;font-size:19px}#replaceModal.renewal-upload-modal .modal-head .close{width:34px;height:34px;border:1px solid #d2e0e8;border-radius:8px;background:#fff;color:#173f59}#replaceModal.renewal-upload-modal .modal-body{padding:14px 18px 0;max-height:none!important;overflow:visible!important}#replaceModal.renewal-upload-modal .renewal-identity{margin-bottom:12px!important}#replaceModal.renewal-upload-modal .form-grid{padding:14px;border:1px solid #dce8ef;border-radius:10px;background:#fff}#replaceModal.renewal-upload-modal .renewal-upload-footer{display:flex;justify-content:flex-end;gap:8px;margin:14px -18px 0;padding:11px 18px 13px;border-top:1px solid #e1e9ee;background:#fafcfd}@media(max-width:700px){#replaceModal.renewal-upload-modal{top:8px!important;max-height:calc(100vh - 16px)!important}#replaceModal.renewal-upload-modal .renewal-identity,#replaceModal.renewal-upload-modal .form-grid{grid-template-columns:1fr!important}}`;
            document.head.append(style);
        }
        const title = modal.querySelector('.modal-head strong');
        if (title) title.textContent = mode === 'replace' ? 'Replace Rejected Document' : 'Renew Employee Document';
        const modalHead = modal.querySelector('.modal-head');
        if (modalHead && !modalHead.querySelector('.renewal-eyebrow')) {
            const headingWrap = document.createElement('div');
            const eyebrow = document.createElement('span');
            eyebrow.className = 'renewal-eyebrow';
            eyebrow.textContent = 'EMPLOYEE DOCUMENT UPLOAD';
            modalHead.insertBefore(headingWrap, title);
            headingWrap.append(eyebrow, title);
        }

        const form = modal.querySelector('form');
        const grid = form?.querySelector('.form-grid');
        if (!form || !grid) return;

        let identity = form.querySelector('.renewal-identity');
        if (!identity) {
            identity = document.createElement('div');
            identity.className = 'renewal-identity';
            identity.style.cssText = 'display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px;margin-bottom:14px;padding:13px;border:1px solid #dce8ef;border-radius:10px;background:#f7fafb';
            identity.innerHTML = '<label>Employee<input data-renew-employee readonly></label><label>Document Category<input data-renew-category readonly></label><label>Document Type<input data-renew-type readonly></label>';
            form.insertBefore(identity, grid);
        }

        identity.querySelector('[data-renew-employee]').value = document.querySelector('.profile-head h2')?.textContent.trim() || '';
        identity.querySelector('[data-renew-category]').value = category;
        identity.querySelector('[data-renew-type]').value = type;
        identity.querySelectorAll('label').forEach((label) => label.style.cssText = 'font-size:12px;font-weight:800;color:#496578');
        identity.querySelectorAll('input').forEach((input) => input.style.cssText = 'display:block;width:100%;margin-top:5px;padding:9px;border:1px solid #cbdce5;border-radius:8px;background:#fff');

        form.querySelector('[name="return_view"]').value = 'profile';
        let returnEmployee = form.querySelector('[name="return_employee_id"]');
        if (!returnEmployee) {
            returnEmployee = document.createElement('input');
            returnEmployee.type = 'hidden';
            returnEmployee.name = 'return_employee_id';
            form.append(returnEmployee);
        }
        returnEmployee.value = employeeId || '';
        const submit = form.querySelector('button.btn.green');
        if (submit) {
            submit.textContent = mode === 'replace' ? 'Upload Replacement for Verification' : 'Upload Renewal for Verification';
            let cancel = form.querySelector('[data-renew-cancel]');
            if (!cancel) {
                cancel = makeButton('Cancel');
                cancel.dataset.renewCancel = '';
                cancel.style.marginRight = '8px';
                cancel.addEventListener('click', () => modal.close());
                submit.before(cancel);
            }
            let footer = form.querySelector('.renewal-upload-footer');
            if (!footer) {
                footer = document.createElement('div');
                footer.className = 'renewal-upload-footer';
                form.append(footer);
            }
            footer.append(cancel, submit);
        }
        if (modal.open) modal.close();
        modal.showModal();
    };

    document.querySelectorAll('.panel .table tbody tr').forEach((row) => {
        const badge = row.querySelector('.badge');
        const view = row.querySelector('a.btn.light');
        if (!badge || !view || row.cells.length !== 4) return;

        const idMatch = view.getAttribute('href')?.match(/download=(\d+)/);
        const documentId = idMatch ? idMatch[1] : '';
        const status = badge.textContent.trim().toUpperCase();
        const type = row.cells[0].textContent.trim();
        const category = row.closest('section.panel')?.querySelector('h2')?.textContent.trim() || '';
        const actions = row.cells[3];

        if (status === 'REJECTED' && documentId) {
            view.removeAttribute('target');
            view.href = '#';
            view.textContent = 'View reason';
            view.addEventListener('click', async (event) => {
                event.preventDefault();
                try {
                    const response = await fetch(`employee_documents.php?document_info=${encodeURIComponent(documentId)}`);
                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Unable to load the rejection reason.');
                    reasonDialog.querySelector('[data-reason-document]').value = type;
                    reasonDialog.querySelector('[data-reason-text]').value = result.remarks;
                    reasonDialog.showModal();
                } catch (error) {
                    reasonDialog.querySelector('[data-reason-document]').value = type;
                    reasonDialog.querySelector('[data-reason-text]').value = error.message || 'Unable to load the rejection reason.';
                    reasonDialog.showModal();
                }
            });
            const replace = makeButton('Replace');
            replace.addEventListener('click', () => {
                window.location.href = `employee_documents.php?view=renew&document_id=${encodeURIComponent(documentId)}`;
            });
            actions.append(replace);
        } else if ((status === 'EXPIRING SOON' || status === 'EXPIRED') && documentId) {
            const renew = makeButton('Renew');
            renew.addEventListener('click', () => {
                window.location.href = `employee_documents.php?view=renew&document_id=${encodeURIComponent(documentId)}`;
            });
            actions.append(renew);
        }
    });

    document.querySelectorAll('.panel .table').forEach((table) => {
        const rows = [...table.querySelectorAll('tbody tr')];
        if (!rows.some((row) => row.querySelector('.badge'))) return;

        table.style.tableLayout = 'fixed';
        rows.forEach((row) => {
            if (row.cells.length !== 4 || !row.querySelector('.badge')) return;
            row.cells[0].style.width = '38%';
            row.cells[1].style.width = '22%';
            row.cells[1].style.textAlign = 'center';
            row.cells[2].style.width = '22%';
            row.cells[2].style.textAlign = 'center';
            row.cells[3].style.width = '18%';
            row.cells[3].style.textAlign = 'center';
        });
    });

    const previewableLinks = document.querySelectorAll('.panel a[href*="download="][target="_blank"]');
    previewableLinks.forEach((link) => {
        link.removeAttribute('target');
        link.addEventListener('click', (event) => {
            event.preventDefault();
            document.querySelector('.profile-pdf-preview')?.remove();
            const row = link.closest('tr');
            const title = row?.cells[0]?.textContent.trim() || 'Employee Document';
            const overlay = document.createElement('div');
            overlay.className = 'profile-pdf-preview';
            overlay.innerHTML = `<section><header><div><small>DOCUMENT PREVIEW</small><strong></strong></div><button type="button" aria-label="Close preview">&times;</button></header><iframe title="PDF document preview"></iframe></section>`;
            overlay.querySelector('strong').textContent = title;
            overlay.querySelector('iframe').src = link.href;
            const closePreview = () => overlay.remove();
            overlay.querySelector('button').addEventListener('click', closePreview);
            overlay.addEventListener('click', (clickEvent) => {
                if (clickEvent.target === overlay) closePreview();
            });
            const closeWithEscape = (keyEvent) => {
                if (keyEvent.key !== 'Escape') return;
                closePreview();
                document.removeEventListener('keydown', closeWithEscape);
            };
            document.addEventListener('keydown', closeWithEscape);
            document.body.append(overlay);
        });
    });
});
