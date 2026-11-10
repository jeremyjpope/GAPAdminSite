<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?>
<div class="btn-group btn-group-sm" role="group">
 <a class="btn <?= $view === 'list' ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= site_url('tuition?view=list') ?>"><i class="bi bi-list-ul"></i> List</a>
 <a class="btn <?= $view === 'grid' ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= site_url('tuition?view=grid') ?>"><i class="bi bi-grid-3x3"></i> By month</a>
</div>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $tDue = $tPaid = $tSched = 0; $mp = fn ($a) => ! empty($a['mp_link']) ? ' <a class="text-success" href="' . esc($a['mp_link']) . '" target="_blank" rel="noopener" title="Payment plan in Ministry Platform"><i class="bi bi-box-arrow-up-right"></i></a>' : ''; ?>
<?php if ($view === 'grid'): ?>
<style>
 .tg { font-size: .78rem; } .tg td, .tg th { vertical-align: top; padding: .2rem .3rem; }
 .tg td.m { min-width: 7rem; } .tg td.got { background: #d9ead3; } .tg td.post { background: #fff; }
 .tg td.paidup { background: #d9ead3; }
 .tg .ref { font-weight: 600; } .tg .nt { color: #555; }
 @media print { .sidebar, .navbar, .btn-group { display: none !important; } }
</style>
<div class="table-responsive"><table class="table table-bordered tg mb-0 bg-white">
 <thead class="table-light"><tr><th>Name</th><th>Payment plan</th><th>Notes</th>
  <?php foreach ($months as $m): ?><th class="text-center"><?= date('F', strtotime($m . '-01')) ?></th><?php endforeach ?>
  <?php if (array_filter(array_map(fn ($b) => $b['other'] ?? null, $byMonth))): ?><th class="text-center">Other dates</th><?php endif ?>
  <th class="text-end">Balance</th></tr></thead>
 <tbody>
 <?php foreach ($students as $s): $a = $accts[$s['id']] ?? null; $p = (float) ($paid[$s['id']]['paid'] ?? 0); $sc = (float) ($paid[$s['id']]['scheduled'] ?? 0); $due = (float) ($a['total_due'] ?? 0); $bal = $due - $p - $sc; $tDue += $due; $tPaid += $p; $tSched += $sc; $paidUp = $a && $due > 0 && $due - $p <= 0; ?>
 <tr>
  <td class="<?= $paidUp ? 'paidup' : '' ?> text-nowrap"><a href="<?= site_url('tuition/' . $s['id']) ?>" class="text-decoration-none fw-semibold text-body"><?= esc($s['first_name']) ?> <?= esc($s['last_name']) ?></a><?= $mp($a) ?></td>
  <td><?= $a ? esc(['year' => 'Year in Full', 'semester' => 'By Semester', 'month' => 'Monthly', 'other' => 'Other'][$a['plan_type']] ?? $a['plan_type']) . '/' . money($due) . ($paidUp ? ' PAID' : '') : '<span class="text-warning">no plan</span>' ?></td>
  <td class="nt" style="max-width:14rem" title="<?= esc($a['notes'] ?? '') ?>"><?= esc(mb_strimwidth((string) ($a['notes'] ?? ''), 0, 90, '…')) ?></td>
  <?php $cols = $months; if (array_filter(array_map(fn ($b) => $b['other'] ?? null, $byMonth))) { $cols[] = 'other'; }
  foreach ($cols as $m): $ps = $byMonth[$s['id']][$m] ?? []; $cls = ''; foreach ($ps as $pm) { $cls = $pm['paid_on'] <= $today ? 'got' : ($cls ?: 'post'); } ?>
  <td class="m <?= $cls ?>"><?php foreach ($ps as $pm): $note = trim((string) $pm['note']); ?><div title="<?= esc($note) ?>"><span class="ref"><?= esc($pm['reference'] ?: ucfirst((string) $pm['method'])) ?></span> <?= money($pm['amount']) ?><?= $note !== '' && ! str_starts_with($note, 'Imported:') ? '<span class="nt">/' . esc(mb_strimwidth($note, 0, 42, '…')) . '</span>' : '' ?><span class="nt">; <?= $pm['paid_on'] <= $today ? 'deposited' : 'dated' ?> <?= fmt_date($pm['paid_on'], 'n/j/y') ?></span></div><?php endforeach ?></td>
  <?php endforeach ?>
  <td class="text-end fw-semibold <?= $a && $bal <= 0 ? 'text-success' : ($bal > 0 ? 'text-danger' : '') ?>"><?= $a ? money($bal) : '' ?></td>
 </tr>
 <?php endforeach ?>
 </tbody>
 <tfoot class="table-light fw-semibold"><tr><td colspan="3">Totals: due <?= money($tDue) ?> · received <?= money($tPaid) ?> · post-dated <?= money($tSched) ?></td><td colspan="<?= count($cols ?? $months) ?>"></td><td class="text-end"><?= money($tDue - $tPaid - $tSched) ?></td></tr></tfoot>
</table></div>
<p class="text-secondary small mt-2">Green cell = payment received that month (reference, amount, note, date). White text in a cell = a post-dated check recorded for that month. A green name = paid in full. <i class="bi bi-box-arrow-up-right text-success"></i> opens the payment plan in Ministry Platform.</p>
<?php else: ?>
<div class="card"><table class="table table-sm table-hover mb-0">
<thead class="table-light"><tr><th>Student</th><th>Plan</th><th class="text-end">Total due</th><th class="text-end">Received</th><th class="text-end">Post-dated</th><th class="text-end">Balance</th><th>Last payment</th><th>Notes</th></tr></thead><tbody>
<?php foreach ($students as $s): $a = $accts[$s['id']] ?? null; $p = (float) ($paid[$s['id']]['paid'] ?? 0); $sc = (float) ($paid[$s['id']]['scheduled'] ?? 0); $due = (float) ($a['total_due'] ?? 0); $bal = $due - $p - $sc; $tDue += $due; $tPaid += $p; $tSched += $sc; ?>
<tr>
  <td><a href="<?= site_url('tuition/' . $s['id']) ?>" class="text-decoration-none fw-semibold"><?= esc($s['last_name']) ?>, <?= esc($s['first_name']) ?></a><?= $mp($a) ?></td>
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
<?php endif ?>
<?= $this->endSection() ?>
