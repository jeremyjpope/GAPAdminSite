<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#homeModal" onclick="editHome(null)"><i class="bi bi-plus-lg"></i> Add host home</button><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="row g-3">
 <div class="col-lg-8">
  <?php if (! $homes): ?><div class="card"><div class="card-body text-secondary">No host homes for <?= esc($year['label']) ?> yet.</div></div><?php endif ?>
  <?php foreach ($homes as $h): ?>
  <div class="card mb-2"><div class="card-body d-flex justify-content-between">
    <div>
      <div class="fw-semibold"><?= esc($h['family_name']) ?></div>
      <div class="small text-secondary"><?= esc($h['address']) ?></div>
      <div class="small"><?= esc($h['email']) ?><?= $h['email2'] ? ' · ' . esc($h['email2']) : '' ?> · <?= esc($h['phone']) ?><?= $h['phone2'] ? ' · ' . esc($h['phone2']) : '' ?></div>
      <?php if ($h['notes']): ?><div class="small text-secondary fst-italic"><?= esc($h['notes']) ?></div><?php endif ?>
      <div class="mt-1"><?php foreach ($byHome[$h['id']] ?? [] as $s): ?><a class="badge text-bg-light text-decoration-none" href="<?= site_url('students/' . $s['id']) ?>"><?= esc(student_name($s)) ?></a> <?php endforeach ?><?= empty($byHome[$h['id']]) ? '<span class="small text-secondary">no students assigned</span>' : '' ?></div>
    </div>
    <div class="text-end text-nowrap">
      <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('notes/host_home/' . $h['id']) ?>" title="Notes"><i class="bi bi-journal-text"></i> <?= $noteCounts[$h['id']] ?? 0 ?></a>
      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#homeModal" onclick='editHome(<?= json_encode($h) ?>)'><i class="bi bi-pencil"></i></button>
      <form class="d-inline" method="post" action="<?= site_url('housing/' . $h['id'] . '/delete') ?>" onsubmit="return confirm('Remove this host home?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    </div>
  </div></div>
  <?php endforeach ?>
 </div>
 <div class="col-lg-4">
  <div class="card"><div class="card-header bg-white fw-semibold">Assignments</div>
   <table class="table table-sm mb-0"><tbody>
   <?php foreach ($students as $s): ?>
   <tr><td class="small"><?= esc(student_name($s)) ?></td><td>
     <form method="post" action="<?= site_url('housing/assign') ?>"><?= csrf_field() ?><input type="hidden" name="student_id" value="<?= $s['id'] ?>">
      <select class="form-select form-select-sm" name="home_id" onchange="this.form.submit()"><option value="0">— at home / none —</option>
      <?php foreach ($homes as $h): ?><option value="<?= $h['id'] ?>" <?= $s['host_home_id'] == $h['id'] ? 'selected' : '' ?>><?= esc($h['family_name']) ?></option><?php endforeach ?></select></form>
   </td></tr>
   <?php endforeach ?>
   </tbody></table></div>
 </div>
</div>
<div class="modal fade" id="homeModal"><div class="modal-dialog"><form class="modal-content" method="post" id="homeForm"><?= csrf_field() ?>
 <div class="modal-header"><h5 class="modal-title">Host home</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body row g-2">
  <div class="col-12"><label class="form-label">Family</label><input class="form-control" name="family_name" placeholder="Scott & Pam Bailey" required></div>
  <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address"></div>
  <div class="col-6"><label class="form-label">Email</label><input class="form-control" name="email"></div>
  <div class="col-6"><label class="form-label">Email 2</label><input class="form-control" name="email2"></div>
  <div class="col-6"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
  <div class="col-6"><label class="form-label">Phone 2</label><input class="form-control" name="phone2"></div>
  <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
 </div>
 <div class="modal-footer"><button class="btn btn-dark">Save</button></div>
</form></div></div>
<script>
function editHome(h){const f=document.getElementById('homeForm');f.action=h?'<?= site_url('housing') ?>/'+h.id:'<?= site_url('housing') ?>';
 for(const k of ['family_name','address','email','email2','phone','phone2','notes']) f.elements[k].value=h?(h[k]||''):'';}
</script>
<?= $this->endSection() ?>
