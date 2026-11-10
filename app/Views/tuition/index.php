<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $tDue = $tPaid = $tSched = 0; ?>
<div class="card"><table class="table table-sm table-hover mb-0">
<thead class="table-light"><tr><th>Student</th><th>Plan</th><th class="text-end">Total due</th><th class="text-end">Received</th><th class="text-end">Post-dated</th><th class="text-end">Balance</th><th>Last payment</th><th>Notes</th></tr></thead><tbody>
<?php foreach ($students as $s): $a = $accts[$s['id']] ?? null; $p = (float) ($paid[$s['id']]['paid'] ?? 0); $sc = (float) ($paid[$s['id']]['scheduled'] ?? 0); $due = (float) ($a['total_due'] ?? 0); $bal = $due - $p - $sc; $tDue += $due; $tPaid += $p; $tSched += $sc; ?>
<tr>
  <td><a href="<?= site_url('tuition/' . $s['id']) ?>" class="text-decoration-none fw-semibold"><?= esc($s['last_name']) ?>, <?= esc($s['first_name']) ?></a></td>
  <td class="small"><?= $a ? esc($a['plan_type']) : '<span class="text-warning">no plan</span>' ?></td>
  <td class="text-end"><?= $a ? money($due) : '' ?></td>
  <td class="text-end"><?= money($p) ?></td>
  <td class="text-end text-secondary"><?= $sc ? money($sc) : '' ?></td>
  <td class="text-end <?= $a && $bal <= 0 ? 'text-success' : ($bal > 0 ? 'text-danger' : '') ?> fw-semibold"><?= $a ? money($bal) : '' ?></td>
  <td class="small"><?= fmt_date($paid[$s['id']]['last_paid'] ?? null) ?></td>
  <td class="small text-secondary text-truncate" style="max-width:260px"><?= esc($a['notes'] ?? '') ?></td>
</tr>
<?php endforeach ?>
</tbody><tfoot class="table-light fw-semibold"><tr><td colspan="2">Totals</td><td class="text-end"><?= money($tDue) ?></td><td class="text-end"><?= money($tPaid) ?></td><td class="text-end"><?= money($tSched) ?></td><td class="text-end"><?= money($tDue - $tPaid - $tSched) ?></td><td colspan="2"></td></tr></tfoot></table></div>
<p class="text-secondary small mt-2">"Post-dated" is money recorded with a future date (post-dated checks). Balance is what is still unaccounted for after received and post-dated amounts. Year tuition for <?= esc($year['label']) ?>: <?= money($year['tuition_year_early']) ?><?= $year['tuition_cutoff_date'] ? ' if applied before ' . fmt_date($year['tuition_cutoff_date']) . ', otherwise ' : ' / ' ?><?= money($year['tuition_year_late']) ?>.</p>
<?= $this->endSection() ?>
