<?php
// Expects an aggregate-only $nursingOverview; never displays patient identities.
$overviewValue = static fn($value) => $value === null ? 'Not reported' : (string) $value;
?>
<section class="nursing-overview-section" aria-labelledby="overview-bed-title">
    <div class="overview-section-heading"><div><span class="overview-eyebrow">HOSPITAL CAPACITY</span><h2 id="overview-bed-title">Bed Availability</h2></div><span>Current bed status</span></div>
    <div class="overview-kpis">
        <?php foreach (['available' => ['Available Beds', 'Ready for assignment'], 'occupied' => ['Occupied Beds', 'Currently acquired / in use'], 'capacity' => ['Total Bed Capacity', 'ER, wards, NICU and ICU'], 'maintenance' => ['Unavailable Beds', 'Under maintenance']] as $key => [$label, $note]): ?>
            <article class="overview-kpi overview-kpi-<?php echo $key; ?>">
                <span class="overview-kpi-icon" aria-hidden="true"><i class="bi bi-<?php echo ['available' => 'check2-circle', 'occupied' => 'person-fill', 'capacity' => 'hospital', 'maintenance' => 'tools'][$key]; ?>"></i></span>
                <span class="overview-kpi-label"><?php echo htmlspecialchars($label); ?></span>
                <strong class="overview-number"><?php echo htmlspecialchars($overviewValue($nursingOverview['totals'][$key])); ?></strong>
                <span class="overview-meta"><?php echo htmlspecialchars($note); ?></span>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<section class="nursing-overview-section" aria-labelledby="overview-census-title">
    <div class="overview-section-heading"><div><span class="overview-eyebrow">NURSING OPERATIONS</span><h2 id="overview-census-title">Department Census</h2></div><span>Patient and activity totals</span></div>
    <div class="overview-census-grid">
        <?php foreach ($nursingOverview['census'] as $department): ?>
            <?php $bed = $nursingOverview['beds'][$department['name']] ?? null; ?>
            <article class="overview-census-card">
                <div class="overview-census-top"><h3><?php echo htmlspecialchars($department['name']); ?></h3><span class="overview-status"><?php echo $department['count'] === null ? 'Not reported' : 'Reported'; ?></span></div>
                <strong class="overview-number"><?php echo htmlspecialchars($overviewValue($department['count'])); ?></strong>
                <p class="overview-meta"><?php echo htmlspecialchars($department['basis']); ?></p>
                <?php if ($bed): ?>
                    <dl class="overview-bed-counts">
                        <div><dt>Available beds</dt><dd><?php echo htmlspecialchars($overviewValue($bed['available'])); ?></dd></div>
                        <div><dt>Occupied beds</dt><dd><?php echo htmlspecialchars($overviewValue($bed['occupied'])); ?></dd></div>
                    </dl>
                    <?php $bedPercent = $bed['capacity'] > 0 && $bed['occupied'] !== null ? min(100, round($bed['occupied'] / $bed['capacity'] * 100)) : null; ?>
                    <?php if ($bedPercent !== null): ?><div class="overview-bed-meter" role="meter" aria-label="<?php echo htmlspecialchars($department['name']); ?> bed occupancy" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $bedPercent; ?>"><span style="width:<?php echo $bedPercent; ?>%"></span></div><?php endif; ?>
                <?php else: ?>
                    <div class="overview-activity-note">Activity census</div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
