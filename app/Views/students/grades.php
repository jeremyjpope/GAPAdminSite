<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('students/' . $s['id']) ?>">Back to student</a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php if ($error): ?><div class="alert alert-warning"><?= esc($error) ?></div>
<?php else: ?>
<p class="text-secondary small">Live from Moodle course <?= (int) $year['moodle_overall_course_id'] ?> (overall standing). Letter is Moodle's own grade letter where shown.</p>
<div class="card"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Item</th><th>Type</th><th class="text-end">Grade</th><th class="text-end">Range</th><th class="text-end">%</th></tr></thead><tbody>
<?php foreach ($items as $it): $isTotal = ($it['itemtype'] ?? '') === 'course' || ($it['itemtype'] ?? '') === 'category'; ?>
<tr class="<?= $isTotal ? 'table-light fw-semibold' : '' ?>">
  <td><?= esc($it['itemname'] ?: (($it['itemtype'] ?? '') === 'course' ? 'Course total' : 'Category total')) ?></td>
  <td class="small text-secondary"><?= esc($it['itemmodule'] ?? $it['itemtype'] ?? '') ?></td>
  <td class="text-end"><?= esc($it['gradeformatted'] ?? '-') ?></td>
  <td class="text-end small text-secondary"><?= esc(html_entity_decode($it['rangeformatted'] ?? '')) ?></td>
  <td class="text-end"><?= esc($it['percentageformatted'] ?? '') ?></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
<?= $this->endSection() ?>
