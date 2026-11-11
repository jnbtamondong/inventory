<?php
/** Rendering only. Controller supplies authorized, paginated database results. */
function nisRenderMovementWorkspace(array $data): void {
    extract($data, EXTR_SKIP);
    ?>
    <div class="nis-workspace">
    <header class="nis-heading"><div><p class="nis-eyebrow">NURSYNC / PATIENT MANAGEMENT</p><h2>Transfers & handover</h2><p>Keep the same patient and encounter through every movement.</p></div><a class="nis-button" href="movement_history.php">Refresh</a></header>
    <div class="nis-notice">Receiving a patient confirms physical arrival. ER and ward receiving requires an available bed. Specialty units retain their clinical intake and safety checks.</div>
    <?php foreach (['error'=>'alert-danger','success'=>'alert-success'] as $key=>$class): if (!empty($$key)): ?><div role="alert" class="alert <?php echo $class; ?>"><?php echo h($$key); ?></div><?php endif; endforeach; ?>
    <section class="nis-metrics" aria-label="Movement overview">
    <?php foreach (['PENDING'=>'Awaiting acceptance','ACCEPTED'=>'Awaiting handover','IN TRANSIT'=>'In transit','RECEIVED'=>'Received'] as $status=>$label): ?>
        <a href="?status=<?php echo urlencode($status); ?>" class="nis-metric"><span><?php echo h($label); ?></span><strong><?php echo (int)($counts[$status]??0); ?></strong><small>View movements →</small></a>
    <?php endforeach; ?></section>
    <section class="nis-panel"><div class="nis-panel-title"><h3>Movement queue</h3><span><?php echo (int)$total; ?> matching records</span></div>
    <form method="get" class="nis-filters" role="search">
        <label>Patient or encounter<input type="search" name="q" value="<?php echo h($search); ?>" placeholder="Name, Hospital Number, encounter"></label>
        <label>Status<select name="status"><option value="">All statuses</option><?php foreach ($statusOptions as $status): ?><option <?php echo $filterStatus===$status?'selected':''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label>
        <label>Department<select name="department"><option value="">All authorized departments</option><?php foreach ($units as $unit): ?><option value="<?php echo h($unit); ?>" <?php echo $filterDepartment===$unit?'selected':''; ?>><?php echo h(nisDepartmentLabel($unit)); ?></option><?php endforeach; ?></select></label>
        <button class="nis-button nis-primary">Apply filters</button><a href="movement_history.php" class="nis-button">Clear</a>
    </form>
    <div class="nis-table-wrap"><table class="nis-table"><thead><tr><th>Patient / encounter</th><th>Movement</th><th>Progress</th><th>Handover & history</th><th>Next action</th></tr></thead><tbody>
    <?php foreach ($rows as $row): $actions=array_values(array_filter(nisMovementTransitions($row['status']),fn($next)=>nisMovementCanAct($row,$next))); ?>
    <tr><td data-label="Patient / encounter"><strong><?php echo h($row['patient_name']); ?></strong><span>HN <?php echo h($row['permanent_patient_id']?:'Not recorded'); ?></span><span>Encounter <?php echo h($row['encounter_id']?:'Not recorded'); ?></span></td>
    <td data-label="Movement"><strong><?php echo h(nisDepartmentLabel($row['source_department'])); ?></strong><span>→ <?php echo h(nisDepartmentLabel($row['destination_department'])); ?></span><small>#<?php echo (int)$row['id']; ?> · <?php echo h($row['created_at']); ?></small></td>
    <td data-label="Progress"><span class="nis-status" data-status="<?php echo h($row['status']); ?>"><?php echo h($row['status']); ?></span><small><?php echo h($row['movement_type']); ?></small></td>
    <td data-label="Handover & history"><details><summary>View reason & handover</summary><p><?php echo nl2br(h($row['reason']??'')); ?></p><p class="nis-history"><?php echo nl2br(h($row['remarks']??'')); ?></p></details></td>
    <td data-label="Next action"><?php if ($actions): ?><form method="post" class="nis-action" data-nis-confirm>
        <?php echo csrfField(); ?><input type="hidden" name="update_movement" value="1"><input type="hidden" name="movement_id" value="<?php echo (int)$row['id']; ?>">
        <label class="nis-sr" for="action-<?php echo (int)$row['id']; ?>">Next movement status</label><select id="action-<?php echo (int)$row['id']; ?>" name="status"><?php foreach ($actions as $next): ?><option><?php echo h($next); ?></option><?php endforeach; ?></select>
        <?php if (in_array('RECEIVED',$actions,true) && in_array(nisDepartment($row['destination_department']),['ER','WARD_2F','WARD_3F'],true)): ?><label>Receiving bed<select name="bed_id" required><option value="">Select available bed</option><?php foreach ($beds as $bed): if (!nisBedMatchesDepartment($bed,$row['destination_department'])) continue; ?><option value="<?php echo (int)$bed['bed_id']; ?>"><?php echo h($bed['bed_number']); ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <label>Reason / handover<textarea name="remarks" maxlength="2000" rows="2" placeholder="Required for handover, rejection or cancellation"></textarea></label><button class="nis-button nis-primary">Confirm action</button>
    </form><?php else: ?><small><?php echo nisMovementTransitions($row['status']) ? 'Awaiting authorized unit action' : 'Recorded history'; ?></small><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5"><div class="nis-empty"><strong>No matching movements</strong><p>Adjust the filters or refresh after a sending unit creates a request.</p></div></td></tr><?php endif; ?>
    </tbody></table></div>
    <nav class="nis-pagination" aria-label="Movement pages"><span>Page <?php echo $page; ?> of <?php echo $pages; ?></span><?php foreach ([-1=>'Previous',1=>'Next'] as $offset=>$label): $target=$page+$offset; if ($target<1||$target>$pages) continue; ?><a class="nis-button" href="?<?php echo h(http_build_query(['q'=>$search,'status'=>$filterStatus,'department'=>$filterDepartment,'page'=>$target])); ?>"><?php echo $label; ?></a><?php endforeach; ?></nav>
    </section>
    <section class="nis-panel"><div class="nis-panel-title"><div><p class="nis-eyebrow">SENDING UNIT</p><h3>Request a transfer</h3></div></div>
    <p class="nis-help">Use the search above to find a patient in your authorized sending units (up to 50 matches). Verify the Hospital Number and encounter. An active movement prevents duplicate requests.</p>
    <form method="post" class="nis-request" data-nis-confirm><?php echo csrfField(); ?><input type="hidden" name="create_movement" value="1">
    <label>Patient / Hospital Number<select name="patient_id" data-nis-patient required><option value="">Select verified patient</option><?php foreach ($patientChoices as $patient): ?><option value="<?php echo (int)$patient['patient_id']; ?>" data-encounter="<?php echo h($patient['patient_code']); ?>"><?php echo h($patient['patient_name'].' · '.($patient['permanent_patient_id']?:'HN not recorded')); ?></option><?php endforeach; ?></select></label><label>Encounter ID<input name="encounter_id" maxlength="30" readonly required data-nis-encounter></label>
    <label>Sending department<select name="source_department" required><?php foreach ($units as $unit): if (!hasPermission('patient_movements','edit',$unit)) continue; ?><option value="<?php echo h($unit); ?>"><?php echo h(nisDepartmentLabel($unit)); ?></option><?php endforeach; ?></select></label>
    <label>Receiving department<select name="destination_department" required><option value="">Select department</option><?php foreach ($units as $unit): ?><option value="<?php echo h($unit); ?>"><?php echo h(nisDepartmentLabel($unit)); ?></option><?php endforeach; ?></select></label>
    <label class="nis-wide">Transfer reason<textarea name="reason" maxlength="1000" required rows="2"></textarea></label><label class="nis-wide">Clinical handover notes<textarea name="remarks" maxlength="2000" rows="2"></textarea></label>
    <div class="nis-wide"><button class="nis-button nis-primary">Send transfer request</button></div></form></section>
    </div>
    <?php
}
