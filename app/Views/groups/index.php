<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#setModal" onclick="editSet(null)"><i class="bi bi-plus-lg"></i> New group set</button><?= $this->endSection() ?>
<?= $this->section('content') ?>
<p class="text-secondary small">A group set is one way of dividing the <?= $studentCount ?> students of <?= esc($year['label'] ?? '') ?>: presentation teams, Friday night groups, accountability partners, and so on. Each student is in at most one group per set.</p>
<?php if (! $sets): ?><div class="card"><div class="card-body text-secondary">No group sets yet. Create one, or run the Excel import.</div></div><?php endif ?>
<div class="row g-3">
<?php foreach ($sets as $set): $gs = $groups[$set['id']] ?? []; $n = array_sum(array_map(fn ($g) => count($members[$g['id']] ?? []), $gs)); ?>
 <div class="col-md-6 col-xl-4"><div class="card h-100">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
   <a class="fw-semibold text-decoration-none" href="<?= site_url('groups/sets/' . $set['id']) ?>"><?= esc($set['name']) ?></a>
   <span class="badge text-bg-light"><?= count($gs) ?> <?= esc(strtolower($set['group_label'])) ?>s · <?= $n ?>/<?= $studentCount ?></span>
  </div>
  <div class="card-body small">
   <?php foreach ($gs as $g): ?>
    <div class="mb-1"><span class="text-secondary"><?= esc($set['group_label']) ?> <?= esc($g['name']) ?>:</span> <?= esc(implode(', ', array_map('student_name', $members[$g['id']] ?? []))) ?: '<span class="text-secondary">—</span>' ?></div>
   <?php endforeach ?>
   <?php if (! $gs): ?><span class="text-secondary">No groups yet.</span><?php endif ?>
  </div>
  <div class="card-footer bg-white text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('groups/sets/' . $set['id'] . '/print') ?>" target="_blank"><i class="bi bi-printer"></i></a> <a class="btn btn-sm btn-outline-dark" href="<?= site_url('groups/sets/' . $set['id']) ?>">Manage</a></div>
 </div></div>
<?php endforeach ?>
</div>
<?= $this->include('groups/_set_modal') ?>
<?= $this->endSection() ?>
