<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-dark" href="<?= site_url('courses/new') ?>"><i class="bi bi-plus-lg"></i> New course</a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form class="row g-2 mb-3 align-items-end" method="get">
 <div class="col-auto"><input class="form-control form-control-sm" name="q" value="<?= esc($q) ?>" placeholder="Search name"></div>
 <div class="col-auto"><select class="form-select form-select-sm" name="pillar" onchange="this.form.submit()"><option value="">All pillars</option>
  <?php foreach ($cfg->pillars as $k => [$l]): ?><option value="<?= $k ?>" <?= $pillar === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
 <div class="col-auto form-check ms-2"><input class="form-check-input" type="checkbox" name="all" value="1" id="all" <?= service('request')->getGet('all') ? 'checked' : '' ?> onchange="this.form.submit()"><label class="form-check-label small" for="all">Include inactive</label></div>
 <div class="col-auto"><button class="btn btn-sm btn-outline-dark">Filter</button></div>
</form>
<div class="card"><table class="table table-sm table-hover mb-0 align-middle">
 <thead class="table-light"><tr><th>Course</th><th>Pillar</th><th>Category</th><th>Audience</th><th>Staff</th><th class="text-end">Sessions <?= esc($year['label'] ?? '') ?></th></tr></thead>
 <tbody>
 <?php foreach ($rows as $r): ?>
  <tr class="<?= $r['active'] ? '' : 'text-secondary' ?>"><td><span class="d-inline-block rounded-circle me-2" style="width:.7rem;height:.7rem;background:<?= course_color($r) ?>"></span><a class="text-decoration-none fw-semibold" href="<?= site_url('courses/' . $r['id']) ?>"><?= esc($r['name']) ?></a><?= $r['active'] ? '' : ' <span class="badge text-bg-light">inactive</span>' ?></td>
   <td class="small"><?= esc($cfg->pillars[$r['pillar']][0] ?? $r['pillar']) ?></td><td class="small"><?= esc($r['category'] ?? '') ?></td><td class="small"><?= esc($cfg->audiences[$r['audience']] ?? '') ?></td>
   <td class="small"><?= esc(implode(', ', $staff[$r['id']] ?? [])) ?></td><td class="text-end small"><?= $counts[$r['id']] ?? 0 ?></td></tr>
 <?php endforeach ?>
 <?php if (! $rows): ?><tr><td colspan="6" class="text-secondary">No courses yet. Add one, or import a map sheet (see README).</td></tr><?php endif ?>
 </tbody></table></div>
<?= $this->endSection() ?>
