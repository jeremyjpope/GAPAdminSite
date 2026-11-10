<!doctype html><html lang="en"><head><meta charset="utf-8"><title><?= esc($set['name']) ?> · GAP <?= esc($year['label'] ?? '') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{padding:24px}@media print{.noprint{display:none}}.card{break-inside:avoid}</style></head><body>
<div class="d-flex justify-content-between align-items-end mb-3"><div><div class="text-secondary small">GAP <?= esc($year['label'] ?? '') ?></div><h1 class="h3 mb-0"><?= esc($set['name']) ?></h1></div><button class="btn btn-dark btn-sm noprint" onclick="print()">Print</button></div>
<div class="row g-3">
<?php foreach ($groups as $g): ?>
 <div class="col-6 col-md-4 col-lg-3"><div class="card"><div class="card-header fw-semibold"><?= esc($set['group_label']) ?> <?= esc($g['name']) ?></div>
  <ul class="list-group list-group-flush"><?php foreach ($members[$g['id']] ?? [] as $s): ?><li class="list-group-item py-1"><?= esc(student_name($s)) ?></li><?php endforeach ?></ul>
  <?php if ($g['notes']): ?><div class="card-footer small text-secondary"><?= esc($g['notes']) ?></div><?php endif ?></div></div>
<?php endforeach ?>
</div></body></html>
