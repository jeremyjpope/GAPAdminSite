<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?>
<div class="d-flex gap-2">
 <form class="d-flex gap-2" method="get">
  <input class="form-control form-control-sm" name="q" placeholder="Search" value="<?= esc($q) ?>">
  <select class="form-select form-select-sm" name="role" onchange="this.form.submit()"><option value="">All roles</option><?php foreach ($cfg->roles as $r): ?><option value="<?= esc($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= esc($r) ?> (<?= $counts[$r] ?? 0 ?>)</option><?php endforeach ?></select>
  <select class="form-select form-select-sm" name="status" onchange="this.form.submit()"><option value="">Any status</option><?php foreach ($cfg->statuses as $k => $l): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select>
  <input type="hidden" name="role" value="<?= esc($role) ?>">
 </form>
 <a class="btn btn-sm btn-dark" href="<?= site_url('volunteers/new') ?>"><i class="bi bi-plus-lg"></i> Add volunteer</a>
</div>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Name</th><th>Roles</th><th>Email</th><th>Phone</th><th>City</th><th>DBC</th><th>Driver</th><th>Status</th></tr></thead><tbody>
<?php if (! $rows): ?><tr><td colspan="8" class="p-4 text-secondary">No volunteers match.</td></tr><?php endif ?>
<?php foreach ($rows as $v): ?>
<tr class="<?= $v['status'] === 'inactive' ? 'text-secondary' : '' ?>">
 <td><a class="fw-semibold text-decoration-none" href="<?= site_url('volunteers/' . $v['id']) ?>"><?= esc($v['last_name']) ?>, <?= esc($v['first_name']) ?></a></td>
 <td class="small"><?php foreach ($roles[$v['id']] ?? [] as $r): ?><span class="badge text-bg-light border"><?= esc($r['role']) ?><?= $r['category'] ? ' · ' . esc($r['category']) : '' ?><?= $r['is_lead'] ? ' ★' : '' ?></span> <?php endforeach ?></td>
 <td class="small"><?= esc($v['email']) ?></td><td class="small"><?= esc($v['phone']) ?></td><td class="small"><?= esc($v['city_state_zip']) ?></td>
 <td class="small"><?= esc($v['member_of_dbc']) ?></td><td class="small"><?= $v['driver_cleared_year'] ?: '' ?></td>
 <td><span class="badge text-bg-<?= $v['status'] === 'active' ? 'success' : ($v['status'] === 'potential' ? 'warning' : 'secondary') ?>"><?= esc($cfg->statuses[$v['status']] ?? $v['status']) ?></span></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<p class="small text-secondary mt-2"><?= count($rows) ?> volunteer(s). ★ = lead for that life-skills area.</p>
<?= $this->endSection() ?>
