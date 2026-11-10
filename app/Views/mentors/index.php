<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#mModal" onclick="editM(null)"><i class="bi bi-plus-lg"></i> Add mentor</button><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="row g-3">
 <div class="col-lg-7"><div class="card"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Mentor</th><th>Contact</th><th>Students</th><th></th></tr></thead><tbody>
  <?php if (! $mentors): ?><tr><td colspan="4" class="text-secondary p-3">No mentors for <?= esc($year['label']) ?> yet.</td></tr><?php endif ?>
  <?php foreach ($mentors as $m): ?>
  <tr><td class="fw-semibold"><?= esc($m['name']) ?> <span class="text-secondary small"><?= esc($m['gender']) ?></span></td><td class="small"><?= esc($m['email']) ?><br><?= esc($m['phone']) ?></td>
   <td><?php foreach ($byMentor[$m['id']] ?? [] as $s): ?><a class="badge text-bg-light text-decoration-none" href="<?= site_url('students/' . $s['id']) ?>"><?= esc(student_name($s)) ?></a> <?php endforeach ?></td>
   <td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#mModal" onclick='editM(<?= json_encode($m) ?>)'><i class="bi bi-pencil"></i></button>
    <form class="d-inline" method="post" action="<?= site_url('mentors/' . $m['id'] . '/delete') ?>" onsubmit="return confirm('Remove mentor?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach ?>
 </tbody></table></div></div>
 <div class="col-lg-5"><div class="card"><div class="card-header bg-white fw-semibold">Assignments</div><table class="table table-sm mb-0"><tbody>
  <?php foreach ($students as $s): ?>
  <tr><td class="small"><?= esc(student_name($s)) ?></td><td><form method="post" action="<?= site_url('mentors/assign') ?>"><?= csrf_field() ?><input type="hidden" name="student_id" value="<?= $s['id'] ?>">
   <select class="form-select form-select-sm" name="mentor_id" onchange="this.form.submit()"><option value="0">— none —</option><?php foreach ($mentors as $m): ?><option value="<?= $m['id'] ?>" <?= $s['mentor_id'] == $m['id'] ? 'selected' : '' ?>><?= esc($m['name']) ?></option><?php endforeach ?></select></form></td></tr>
  <?php endforeach ?>
 </tbody></table></div></div>
</div>
<div class="modal fade" id="mModal"><div class="modal-dialog"><form class="modal-content" method="post" id="mForm"><?= csrf_field() ?>
 <div class="modal-header"><h5 class="modal-title">Mentor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body row g-2">
  <div class="col-8"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
  <div class="col-4"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="">—</option><option value="M">Male</option><option value="F">Female</option></select></div>
  <div class="col-7"><label class="form-label">Email</label><input class="form-control" name="email"></div>
  <div class="col-5"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
  <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
 </div><div class="modal-footer"><button class="btn btn-dark">Save</button></div></form></div></div>
<script>function editM(m){const f=document.getElementById('mForm');f.action=m?'<?= site_url('mentors') ?>/'+m.id:'<?= site_url('mentors') ?>';for(const k of ['name','gender','email','phone','notes'])f.elements[k].value=m?(m[k]||''):'';}</script>
<?= $this->endSection() ?>
