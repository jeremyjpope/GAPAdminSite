<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('volunteers?role=Guys+Mentor') ?>">Guys mentors</a> <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('volunteers?role=Girls+Mentor') ?>">Girls mentors</a> <a class="btn btn-sm btn-dark" href="<?= site_url('volunteers/new?role=Guys+Mentor') ?>"><i class="bi bi-plus-lg"></i> New mentor</a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<p class="text-secondary small">Mentors are volunteers with a mentor role. Edit their details or roles in <a href="<?= site_url('volunteers') ?>">Volunteers</a>; assign them to students here.</p>
<div class="row g-3">
 <div class="col-lg-7"><div class="card"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Mentor</th><th>Contact</th><th>Students</th></tr></thead><tbody>
  <?php if (! $mentors): ?><tr><td colspan="3" class="text-secondary p-3">No volunteers hold a mentor role yet.</td></tr><?php endif ?>
  <?php foreach ($mentors as $m): $r = array_column($roles[$m['id']] ?? [], 'role'); $isM = (bool) array_intersect($r, ['Guys Mentor', 'Girls Mentor']); ?>
  <tr><td><a class="fw-semibold text-decoration-none" href="<?= site_url('volunteers/' . $m['id']) ?>"><?= esc($m['first_name'] . ' ' . $m['last_name']) ?></a> <span class="text-secondary small"><?= in_array('Girls Mentor', $r, true) ? 'Girls' : (in_array('Guys Mentor', $r, true) ? 'Guys' : '') ?></span><?= $isM ? '' : ' <span class="badge text-bg-warning">no mentor role</span>' ?></td>
   <td class="small"><?= esc($m['email']) ?><br><?= esc($m['phone']) ?></td>
   <td><?php foreach ($byMentor[$m['id']] ?? [] as $s): ?><a class="badge text-bg-light text-decoration-none" href="<?= site_url('students/' . $s['id']) ?>"><?= esc(student_name($s)) ?></a> <?php endforeach ?></td></tr>
  <?php endforeach ?>
 </tbody></table></div></div>
 <div class="col-lg-5"><div class="card"><div class="card-header bg-white fw-semibold">Assignments</div><table class="table table-sm mb-0"><tbody>
  <?php foreach ($students as $s): ?>
  <tr class="<?= $s['mentor_id'] ? '' : 'table-warning' ?>"><td class="small"><?= esc(student_name($s)) ?></td><td><form method="post" action="<?= site_url('mentors/assign') ?>"><?= csrf_field() ?><input type="hidden" name="student_id" value="<?= $s['id'] ?>">
   <select class="form-select form-select-sm" name="mentor_id" onchange="this.form.submit()"><option value="0">— none —</option><?php foreach ($mentors as $m): ?><option value="<?= $m['id'] ?>" <?= $s['mentor_id'] == $m['id'] ? 'selected' : '' ?>><?= esc($m['first_name'] . ' ' . $m['last_name']) ?></option><?php endforeach ?></select></form></td></tr>
  <?php endforeach ?>
 </tbody></table></div></div>
</div>
<?= $this->endSection() ?>
