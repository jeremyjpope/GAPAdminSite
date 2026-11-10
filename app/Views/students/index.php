<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?>
<div class="d-flex gap-2">
  <form class="d-flex" method="get"><input class="form-control form-control-sm" name="q" placeholder="Search" value="<?= esc($q) ?>"></form>
  <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('students/sync-moodle') ?>"><i class="bi bi-arrow-repeat"></i> Sync from Moodle</a>
  <a class="btn btn-sm btn-dark" href="<?= site_url('students/new') ?>"><i class="bi bi-plus-lg"></i> Add student</a>
</div>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card"><table class="table table-sm table-hover mb-0">
<thead class="table-light"><tr><th></th><th>Name</th><th>Email</th><th>Phone</th><th>Host home</th><th>Mentor</th><th>Status</th><th>Moodle</th></tr></thead>
<tbody>
<?php if (! $students): ?><tr><td colspan="8" class="p-4 text-secondary">No students for <?= esc($year['label'] ?? 'this year') ?> yet.</td></tr><?php endif ?>
<?php foreach ($students as $s): ?>
<tr>
  <td style="width:56px"><?php if ($s['photo_path']): ?><img class="avatar" src="<?= site_url('photos/' . $s['id']) ?>" alt=""><?php else: ?><div class="avatar d-flex align-items-center justify-content-center text-secondary"><i class="bi bi-person"></i></div><?php endif ?></td>
  <td><a href="<?= site_url('students/' . $s['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($s['last_name']) ?>, <?= esc($s['first_name']) ?></a><?= $s['preferred_name'] ? ' <span class="text-secondary small">(' . esc($s['preferred_name']) . ')</span>' : '' ?></td>
  <td class="small"><?= esc($s['email']) ?></td>
  <td class="small"><?= esc($s['phone']) ?></td>
  <td class="small"><?= esc($homes[$s['host_home_id']] ?? '') ?></td>
  <td class="small"><?= esc($mentors[$s['mentor_id']] ?? '') ?></td>
  <td><span class="badge text-bg-<?= $s['status'] === 'enrolled' ? 'success' : ($s['status'] === 'graduated' ? 'primary' : 'secondary') ?>"><?= esc($s['status']) ?></span></td>
  <td class="small"><?= $s['moodle_userid'] ? '<i class="bi bi-check-circle text-success"></i> ' . $s['moodle_userid'] : '<span class="text-secondary">not linked</span>' ?></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?= $this->endSection() ?>
