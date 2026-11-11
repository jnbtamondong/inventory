(() => {
    const toolbar = document.querySelector('.toolbar');
    const closeLink = toolbar?.querySelector('a');
    const printButton = toolbar?.querySelector('button');
    if (closeLink) {
        closeLink.className = 'preview-toolbar-button preview-close-button';
        closeLink.textContent = 'Close';
        closeLink.title = 'Close';closeLink.setAttribute('aria-label','Close preview');
        closeLink.href = '#';
        closeLink.addEventListener('click', event => {
            event.preventDefault();
            window.close();
            if (!window.closed) window.location.href = 'ppmp.php';
        });
    }
    if (printButton) {
        printButton.className = 'preview-toolbar-button preview-print-button';
        printButton.textContent = 'Print';
        printButton.title = 'Print';printButton.setAttribute('aria-label','Print PPMP');
    }
    if (toolbar && printButton && closeLink) toolbar.insertBefore(printButton, closeLink);

    const ppmpId = new URLSearchParams(location.search).get('id');
    if (toolbar && ppmpId) fetch(`ppmp_print.php?id=${encodeURIComponent(ppmpId)}&review_comment=1`)
        .then(response => response.json())
        .then(review => {
            const showCommentNotice=(message,type='success')=>{document.querySelector('.review-save-notice')?.remove();const notice=document.createElement('div');notice.className=`review-save-notice ${type}`;notice.setAttribute('role','status');notice.textContent=message;toolbar.after(notice);requestAnimationFrame(()=>notice.classList.add('visible'));setTimeout(()=>{notice.classList.remove('visible');setTimeout(()=>notice.remove(),200)},2600)};
            if (review.status === 'FOR APPROVAL') {
                printButton.hidden = true;
                printButton.style.display = 'none';
                document.body.classList.add('printing-disabled');
            } else {
                printButton.hidden = false;
                printButton.style.display = 'inline-flex';
            }
            const comments = new Map();(review.comments||[]).forEach(item=>{const key=String(item.item_id),items=comments.get(key)||[];items.push(item);comments.set(key,items)});
            const generalComment=review.general_comment;
            if(generalComment&&review.status!=='APPROVED'){const banner=document.createElement('div');banner.className='review-general-banner';const history=(generalComment.follow_ups||[]).map((item,index)=>`Follow-up ${index+1}: ${item.comment}`).join('\n');banner.textContent=`GENERAL COMMENT: ${generalComment.comment}${history?'\n'+history:''}`;toolbar.after(banner)}
            const generalDialog=document.createElement('dialog');generalDialog.className='review-comment-dialog';generalDialog.innerHTML='<form method="dialog"><h2>General PPMP Comment</h2><p>This comment applies to the entire PPMP, not a specific item.</p><label>General Comment<textarea data-general-comment rows="6" maxlength="5000"></textarea></label><div class="review-comment-history" data-general-history hidden></div><label data-general-follow-up hidden>Follow-up Remark<textarea data-general-follow-up-text rows="4" maxlength="5000"></textarea></label><div><button class="preview-toolbar-button" value="cancel">Cancel</button><button class="preview-toolbar-button preview-print-button" value="save">Save Comment</button></div></form>';document.body.append(generalDialog);
            const generalButton=document.createElement('button');generalButton.type='button';generalButton.className='preview-toolbar-button preview-general-comment-button';generalButton.textContent='General Comment';generalButton.hidden=!review.can_edit;toolbar.insertBefore(generalButton,printButton);
            const renderGeneralHistory=()=>{const history=generalDialog.querySelector('[data-general-history]'),items=generalComment?.follow_ups||[];history.hidden=!items.length;history.replaceChildren();if(!items.length)return;const heading=document.createElement('strong');heading.className='follow-up-heading';heading.textContent='FOLLOW-UP COMMENTS';history.append(heading);items.forEach((item,index)=>{const row=document.createElement('span');row.className='follow-up-history-row';const text=document.createElement('span');text.textContent=`${index+1}. ${item.comment}`;row.append(text);if(review.can_edit){const remove=document.createElement('button');remove.type='button';remove.className='follow-up-delete';remove.textContent='×';remove.title='Delete follow-up comment';remove.onclick=async()=>{if(!confirm('Delete this follow-up comment?'))return;const data=new FormData();data.set('csrf_token',review.csrf_token);data.set('review_action','delete_general_follow_up');data.set('follow_up_index',index);try{const response=await fetch(`ppmp_print.php?id=${encodeURIComponent(ppmpId)}`,{method:'POST',body:data});const result=await response.json();if(!response.ok)throw new Error(result.error||'Unable to delete the follow-up comment.');generalComment.follow_ups.splice(index,1);renderGeneralHistory();showCommentNotice('Follow-up comment deleted successfully.','deleted')}catch(error){alert(error.message)}};row.append(remove)}history.append(row)})};
            generalButton.onclick=()=>{const original=generalDialog.querySelector('[data-general-comment]'),followField=generalDialog.querySelector('[data-general-follow-up]');original.value=generalComment?.comment||'';original.readOnly=Boolean(generalComment);followField.hidden=!generalComment;generalDialog.querySelector('[data-general-follow-up-text]').value='';renderGeneralHistory();generalDialog.returnValue='';generalDialog.showModal()};
            generalButton.addEventListener('click',()=>{generalDialog.querySelectorAll('.follow-up-delete').forEach((button,index)=>{button.hidden=Number(generalComment?.follow_ups?.[index]?.revision_round)!==Number(review.revision_round)})});
            generalDialog.addEventListener('close',async()=>{if(generalDialog.returnValue!=='save')return;const comment=generalDialog.querySelector('[data-general-comment]').value.trim(),followUp=generalDialog.querySelector('[data-general-follow-up-text]').value.trim();if(generalComment&&!followUp){alert('Enter a follow-up remark.');setTimeout(()=>generalDialog.showModal());return}const data=new FormData();data.set('csrf_token',review.csrf_token);data.set('review_action','save_general');data.set('review_comment',comment);data.set('follow_up_comment',followUp);try{const response=await fetch(`ppmp_print.php?id=${encodeURIComponent(ppmpId)}`,{method:'POST',body:data});const result=await response.json();if(!response.ok)throw new Error(result.error||'Unable to save the general comment.');showCommentNotice(followUp?'Follow-up comment added successfully.':'General comment added successfully.');setTimeout(()=>location.reload(),900)}catch(error){alert(error.message)}});
            const changes = new Map((review.changes || []).map(item => [String(item.item_id), item]));
            if (changes.size || (review.removed_items || []).length) {
                const summary = document.createElement('div');
                summary.className = 'review-change-banner';
                const changedCount = [...changes.values()].filter(item => ['UPDATED','REVISED'].includes(item.change_type)).length;
                const addedCount = [...changes.values()].filter(item => item.change_type === 'ADDED').length;
                const removedCount = (review.removed_items || []).length;
                summary.textContent = [`${changedCount} updated`, `${addedCount} added`, `${removedCount} removed`].join(' · ');
                if (removedCount) summary.textContent += `\nRemoved: ${review.removed_items.map(item => item.item_description).join(', ')}`;
                toolbar.after(summary);
            }
            const dialog = document.createElement('dialog');
            dialog.className = 'review-comment-dialog';
            dialog.innerHTML = '<form method="dialog"><h2>Item Review Comment</h2><p>Select Quantity or Unit Price and enter the required correction.</p><label>Field<select data-review-field><option>QUANTITY</option><option>UNIT PRICE</option></select></label><label data-new-comment-field>Comment<textarea data-review-comment rows="4" maxlength="5000"></textarea></label><div class="review-comment-history" data-comment-history hidden></div><label data-follow-up-field hidden>Follow-up Comment<textarea data-follow-up-comment rows="4" maxlength="5000" placeholder="Enter the next correction required"></textarea></label><div><button class="preview-toolbar-button" value="cancel">Close</button><button class="preview-toolbar-button row-review-approve" type="button" data-approve-revision hidden>Approve Revision</button><button class="preview-toolbar-button preview-print-button" value="save" data-save-comment>Save Comment</button></div></form>';
            document.body.append(dialog);
            dialog.querySelector('[data-save-comment]').hidden=!review.can_edit;
            const selectedIds = new Set();
            const commentButton = document.createElement('button');
            commentButton.type = 'button';
            commentButton.className = 'preview-toolbar-button preview-comment-button';
            commentButton.disabled = true;
            commentButton.hidden = !review.can_edit;
            toolbar.insertBefore(commentButton, printButton);
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'preview-toolbar-button preview-delete-comment-button';
            deleteButton.textContent = 'Delete Comment';
            deleteButton.title = 'Select commented rows to delete their comments';
            deleteButton.setAttribute('aria-label', deleteButton.title);
            deleteButton.disabled = true;
            deleteButton.hidden = !review.can_edit || review.show_review_status;
            toolbar.insertBefore(deleteButton, printButton);
            let dragging = false;
            const syncSelectionButton = () => {
                commentButton.textContent = selectedIds.size ? `Add Comment (${selectedIds.size})` : 'Add Comment';
                commentButton.title = selectedIds.size ? `Add comment to ${selectedIds.size} selected row(s)` : 'Select rows to add a comment';
                commentButton.setAttribute('aria-label',commentButton.title);
                commentButton.disabled = !selectedIds.size;
                const commentCount = [...selectedIds].filter(id => (comments.get(id)||[]).length).length;
                deleteButton.textContent = commentCount ? `Delete Comment (${commentCount})` : 'Delete Comment';
                deleteButton.title = commentCount ? `Delete comments from ${commentCount} selected row(s)` : 'Select commented rows to delete their comments';
                deleteButton.setAttribute('aria-label', deleteButton.title);
                deleteButton.disabled = !commentCount || review.show_review_status;
            };
            const decorateRows = () => {
                document.querySelectorAll('.ppmp-page .row-review-comment').forEach(note => note.remove());
                const nextNoteTopByPage = new Map();
                document.querySelectorAll('.ppmp-page tbody tr[data-ppmp-item-id]').forEach(row => {
                const saved = comments.get(row.dataset.ppmpItemId)||[];
                row.classList.toggle('has-review-comment', review.status!=='APPROVED'&&saved.length>0);
                row.classList.toggle('revised-review-row', saved.length>0&&saved.every(item=>['REVISED','RESOLVED'].includes(item.resolution_status)));
                row.classList.toggle('selected-review-row', selectedIds.has(row.dataset.ppmpItemId));
                const change = changes.get(row.dataset.ppmpItemId);
                row.classList.toggle('changed-review-row', review.status!=='APPROVED'&&Boolean(change));
                if (saved.length || change) {
                    const note = document.createElement('span');
                    note.className = `row-review-comment${change?' has-change-summary':''}`;
                    const details = [];
                    if (change?.change_type === 'ADDED') details.push('ADDED IN REVISION');
                    else if (change?.change_type === 'REVISED') details.push('RESUBMITTED FOR REVIEW');
                    else if (change) {
                        if (Number(change.previous_quantity) !== Number(change.current_quantity)) details.push(`Quantity: ${change.previous_quantity} → ${change.current_quantity}`);
                        if (Number(change.previous_unit_cost) !== Number(change.current_unit_cost)) details.push(`Unit cost: ₱${Number(change.previous_unit_cost).toLocaleString('en-PH',{minimumFractionDigits:2})} → ₱${Number(change.current_unit_cost).toLocaleString('en-PH',{minimumFractionDigits:2})}`);
                    }
                    saved.forEach(item=>{details.push(`${item.review_field}: ${item.comment}${review.show_review_status?' - '+item.resolution_status:''}`);(item.follow_ups||[]).forEach((followUp,index)=>details.push(`FOLLOW-UP ${index+1}: ${followUp.comment}${followUp.reviewed_by?' - '+followUp.reviewed_by:''}`))});
                    const noteText=document.createElement('span');noteText.textContent=details.join('\n\n');note.append(noteText);
                    if(review.can_edit&&review.show_review_status&&saved.some(item=>item.resolution_status==='REVISED')){
                        const actions=document.createElement('span');actions.className='row-review-actions';
                        const approveButton=document.createElement('button');approveButton.type='button';approveButton.className='row-review-approve';approveButton.textContent='✓';approveButton.title='Approve this item revision';approveButton.setAttribute('aria-label',approveButton.title);approveButton.disabled=!saved.some(item=>item.resolution_status==='REVISED');
                        approveButton.onclick=async event=>{event.stopPropagation();if(approveButton.disabled)return;const data=new FormData();data.set('csrf_token',review.csrf_token);data.set('ppmp_item_id',row.dataset.ppmpItemId);data.set('review_action','resolve_item');try{const response=await fetch(`ppmp_print.php?id=${encodeURIComponent(ppmpId)}`,{method:'POST',body:data});const result=await response.json();if(!response.ok)throw new Error(result.error||'Unable to approve this item revision.');saved.forEach(item=>{if(item.resolution_status==='REVISED')item.resolution_status='RESOLVED'});decorateRows();syncSelectionButton()}catch(error){alert(error.message)}};
                        const reviseButton=document.createElement('button');reviseButton.type='button';reviseButton.className='row-review-revise';reviseButton.textContent='×';reviseButton.title='Request another revision';reviseButton.setAttribute('aria-label',reviseButton.title);reviseButton.disabled=!saved.some(item=>item.resolution_status==='REVISED'&&['QUANTITY','UNIT PRICE'].includes(item.review_field));reviseButton.onclick=event=>{event.stopPropagation();if(reviseButton.disabled)return;selectedIds.clear();selectedIds.add(row.dataset.ppmpItemId);const target=saved.find(item=>item.resolution_status==='REVISED'&&['QUANTITY','UNIT PRICE'].includes(item.review_field));dialog.querySelector('[data-review-field]').value=target.review_field;decorateRows();syncSelectionButton();commentButton.click()};
                        actions.append(approveButton,reviseButton);note.append(actions);
                    }
                    const page = row.closest('.ppmp-page');
                    const rowTop=row.getBoundingClientRect().top-page.getBoundingClientRect().top;
                    const noteTop=Math.max(rowTop,nextNoteTopByPage.get(page)||0);
                    note.style.top = `${noteTop}px`;
                    note.style.minHeight = `${row.getBoundingClientRect().height}px`;
                    page.append(note);
                    nextNoteTopByPage.set(page,noteTop+note.getBoundingClientRect().height+8);
                }
                if(review.can_edit){
                    row.classList.add('reviewable-row');row.title='Double-click to comment, or click and drag to select rows';
                    row.onmousedown=event=>{if(event.button!==0)return;dragging=true;if(!event.ctrlKey&&!event.metaKey)selectedIds.clear();if((event.ctrlKey||event.metaKey)&&selectedIds.has(row.dataset.ppmpItemId))selectedIds.delete(row.dataset.ppmpItemId);else selectedIds.add(row.dataset.ppmpItemId);decorateRows();syncSelectionButton();event.preventDefault()};
                    row.onmouseenter=event=>{if(!dragging||!(event.buttons&1))return;selectedIds.add(row.dataset.ppmpItemId);decorateRows();syncSelectionButton()};
                    row.ondblclick=event=>{event.preventDefault();selectedIds.clear();selectedIds.add(row.dataset.ppmpItemId);decorateRows();syncSelectionButton();commentButton.click()};
                }else if(saved.length){
                    row.classList.add('reviewable-row');row.title='Double-click to view reviewer comments';
                    row.ondblclick=event=>{event.preventDefault();selectedIds.clear();selectedIds.add(row.dataset.ppmpItemId);const target=saved.find(item=>['QUANTITY','UNIT PRICE'].includes(item.review_field))||saved[0];if(['QUANTITY','UNIT PRICE'].includes(target.review_field))dialog.querySelector('[data-review-field]').value=target.review_field;loadCommentForm();dialog.returnValue='';dialog.showModal()};
                }
                });
            };
            decorateRows();
            syncSelectionButton();
            document.addEventListener('mouseup',()=>{dragging=false});
            document.addEventListener('mousedown',event=>{
                if(event.target.closest('.ppmp-page tbody tr[data-ppmp-item-id], .row-review-comment, .preview-comment-button, .preview-delete-comment-button, .review-comment-dialog'))return;
                if(!selectedIds.size)return;
                selectedIds.clear();
                decorateRows();
                syncSelectionButton();
            });
            const loadCommentForm=()=>{
                const field=dialog.querySelector('[data-review-field]').value,selectedComments=[...selectedIds].map(id=>(comments.get(id)||[]).find(item=>item.review_field===field));
                const existing=selectedComments.filter(Boolean),allHaveComments=selectedComments.length>0&&existing.length===selectedComments.length,canFollowUp=review.can_edit&&allHaveComments;
                dialog.querySelector('[data-review-comment]').value=selectedComments.length&&selectedComments.every(item=>item?.comment===selectedComments[0]?.comment)?selectedComments[0]?.comment||'':'';
                dialog.querySelector('[data-review-comment]').readOnly=existing.length>0||!review.can_edit;
                dialog.querySelector('[data-new-comment-field]').hidden=existing.length>0;
                const history=dialog.querySelector('[data-comment-history]'),first=existing[0],followUps=first?.follow_ups||[],itemId=[...selectedIds][0],change=changes.get(String(itemId)),historyParts=[];
                if(first){historyParts.push(`${first.review_field}\n${first.comment}`);if(change){if(first.review_field==='QUANTITY'&&change.previous_quantity!==null)historyParts.push(`REVISION HISTORY\nQuantity: ${change.previous_quantity} to ${change.current_quantity}`);if(first.review_field==='UNIT PRICE'&&change.previous_unit_cost!==null)historyParts.push(`REVISION HISTORY\nUnit Price: ₱${Number(change.previous_unit_cost).toLocaleString('en-PH',{minimumFractionDigits:2})} to ₱${Number(change.current_unit_cost).toLocaleString('en-PH',{minimumFractionDigits:2})}`)}if(review.show_review_status)historyParts.push(`STATUS\n${first.resolution_status}`)}history.hidden=!first;history.textContent=historyParts.join('\n\n');if(first&&followUps.length){const heading=document.createElement('strong');heading.className='follow-up-heading';heading.textContent='FOLLOW-UP COMMENTS';history.append(heading);followUps.forEach((item,index)=>{const row=document.createElement('span');row.className='follow-up-history-row';const text=document.createElement('span');text.textContent=`${index+1}. ${item.comment}`;row.append(text);if(review.can_edit){const remove=document.createElement('button');remove.type='button';remove.className='follow-up-delete';remove.textContent='×';remove.title='Delete follow-up comment';remove.setAttribute('aria-label',remove.title);remove.onclick=async()=>{if(!confirm('Delete this follow-up comment?'))return;const data=new FormData();data.set('csrf_token',review.csrf_token);data.set('review_action','delete_follow_up');data.set('ppmp_item_id',itemId);data.set('review_field',first.review_field);data.set('follow_up_index',index);try{const response=await fetch(`ppmp_print.php?id=${encodeURIComponent(ppmpId)}`,{method:'POST',body:data});const result=await response.json();if(!response.ok)throw new Error(result.error||'Unable to delete the follow-up comment.');first.follow_ups.splice(index,1);loadCommentForm();showCommentNotice('Follow-up comment deleted successfully.','deleted')}catch(error){alert(error.message)}};row.append(remove)}history.append(row)})}
                history.querySelectorAll('.follow-up-delete').forEach((button,index)=>{button.hidden=Number(followUps[index]?.revision_round)!==Number(review.revision_round)});
                dialog.querySelector('[data-follow-up-field]').hidden=!canFollowUp;
                dialog.querySelector('[data-follow-up-comment]').value='';
                dialog.querySelector('[data-save-comment]').hidden=!review.can_edit||(existing.length>0&&!canFollowUp);
                dialog.querySelector('[data-approve-revision]').hidden=!(review.can_edit&&allHaveComments&&existing.some(item=>item.resolution_status==='REVISED'));
            };
            commentButton.onclick=()=>{
                loadCommentForm();
                dialog.returnValue='';
                dialog.showModal();
            };
            dialog.querySelector('[data-review-field]').onchange=loadCommentForm;
            dialog.querySelector('[data-approve-revision]').onclick=async event=>{const button=event.currentTarget,itemId=[...selectedIds][0];if(!itemId)return;button.disabled=true;const data=new FormData();data.set('csrf_token',review.csrf_token);data.set('ppmp_item_id',itemId);data.set('review_action','resolve_item');try{const response=await fetch(`ppmp_print.php?id=${encodeURIComponent(ppmpId)}`,{method:'POST',body:data});const result=await response.json();if(!response.ok)throw new Error(result.error||'Unable to approve this item revision.');(comments.get(String(itemId))||[]).forEach(item=>{if(item.resolution_status==='REVISED')item.resolution_status='RESOLVED'});button.hidden=true;selectedIds.clear();dialog.close();decorateRows();syncSelectionButton();showCommentNotice('Item revision approved successfully.')}catch(error){button.disabled=false;alert(error.message)}};
            const saveComment = async (itemIds, comment, deleteAll=false, followUp='') => {
                const field=dialog.querySelector('[data-review-field]').value;
                const results=await Promise.all(itemIds.map(async itemId=>{const data=new FormData();data.set('csrf_token',review.csrf_token);data.set('review_comment',comment);data.set('follow_up_comment',followUp);data.set('review_field',field);data.set('ppmp_item_id',itemId);if(deleteAll)data.set('delete_all','1');const response=await fetch(`ppmp_print.php?id=${encodeURIComponent(ppmpId)}`,{method:'POST',body:data});const result=await response.json();if(!response.ok)throw new Error(result.error||'Unable to save the comment.');return result}));
                results.forEach(result=>{const key=String(result.item_id);if(deleteAll){comments.delete(key);return}const items=(comments.get(key)||[]).filter(item=>item.review_field!==result.review_field);if(result.comment)items.push(result);if(items.length)comments.set(key,items);else comments.delete(key)});
                decorateRows();
                syncSelectionButton();
            };
            deleteButton.onclick=async()=>{
                const commentedIds=[...selectedIds].filter(id=>(comments.get(id)||[]).length);
                if(!commentedIds.length||!confirm(`Delete comments from ${commentedIds.length} selected row(s)?`))return;
                try{await saveComment(commentedIds,'',true);showCommentNotice(commentedIds.length===1?'Comment deleted successfully.':`${commentedIds.length} comments deleted successfully.`,'deleted')}catch(error){alert(error.message)}
            };
            dialog.addEventListener('close', async () => {
                if (dialog.returnValue !== 'save' || !selectedIds.size) return;
                const comment=dialog.querySelector('[data-review-comment]').value,followUp=dialog.querySelector('[data-follow-up-comment]').value.trim();
                if(!dialog.querySelector('[data-follow-up-field]').hidden&&!followUp){alert('Enter a follow-up remark for the revised item.');setTimeout(()=>dialog.showModal());return}
                try{
                    await saveComment([...selectedIds],comment,false,followUp);
                    showCommentNotice(followUp?'Follow-up comment added successfully.':'Comment added successfully.');
                }catch(error){alert(error.message)}
            });
        });

    const source = document.querySelector('.ppmp-print-sheet');
    const preview = document.querySelector('.ppmp-preview-scroll');
    const table = source.querySelector('.ppmp-plan-table');
    const rows = [...table.tBodies[0].rows].map(row => row.cloneNode(true));
    const headingSelectors = ['.print-head', '.title', '.ppmp-fiscal-year', '.ppmp-unit-meta'];
    const pages = [];
    let content, currentTable;

    function newPage() {
        const page = document.createElement('section');
        page.className = 'ppmp-page';
        headingSelectors.forEach(selector => page.append(source.querySelector(selector).cloneNode(true)));
        content = document.createElement('div');
        content.className = 'ppmp-page-content';
        currentTable = table.cloneNode(false);
        currentTable.append(table.querySelector('colgroup').cloneNode(true), table.tHead.cloneNode(true));
        currentTable.createTBody();
        content.append(currentTable);
        const footer = source.querySelector('.print-foot').cloneNode(true);
        page.append(content, footer);
        preview.append(page);
        pages.push(page);
    }

    function overflows() {
        return content.scrollHeight > content.clientHeight + 1;
    }

    // Split unusually long text rows into continuation rows rather than cover the footer.
    function splitRow(row) {
        const first = row.cloneNode(true), rest = row.cloneNode(true);
        let split = false;
        [...row.cells].forEach((cell, index) => {
            const text = cell.textContent.trim();
            let cut = Math.ceil(text.length / 2);
            if (text.length > 1) {
                const space = text.lastIndexOf(' ', cut);
                if (space > 0) cut = space;
                split = true;
            } else cut = text.length;
            first.cells[index].textContent = text.slice(0, cut).trim();
            rest.cells[index].textContent = text.slice(cut).trim();
        });
        return split ? [first, rest] : null;
    }

    source.hidden = true;
    newPage();
    while (rows.length) {
        const row = rows.shift();
        currentTable.tBodies[0].append(row);
        if (!overflows()) continue;
        row.remove();
        if (currentTable.tBodies[0].rows.length) {
            newPage();
            rows.unshift(row);
        } else {
            const parts = splitRow(row);
            if (parts) rows.unshift(...parts);
            else currentTable.tBodies[0].append(row);
        }
    }
    const totals = table.tFoot.cloneNode(true);
    const signatures = source.querySelector('.signatures').cloneNode(true);
    currentTable.append(totals);
    content.append(signatures);
    if (overflows()) {
        totals.remove();
        signatures.remove();
        newPage();
        currentTable.append(totals);
        content.append(signatures);
    }
    pages.forEach((page, index) => {
        page.setAttribute('aria-label', `PPMP page ${index + 1} of ${pages.length}`);
    });
    source.remove();
})();
