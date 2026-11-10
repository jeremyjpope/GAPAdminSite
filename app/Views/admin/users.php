<?= $this->extend('layouts/main') ?>
<?php use App\Libraries\Auth; ?>
<?= $this->section('actions') ?><button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#uModal" onclick="editU(null)"><i class="bi bi-plus-lg"></i> Grant access</button><?= $this->endSection() ?>
<?= $this->section('content') ?>
<p class="text-secondary small">People log in with their Moodle account. They get in only if they appear here and are active. Match is by Moodle user id, then username, then email.</p>
<div class="card"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Name</th><th>Moodle login</th><th>Areas</th><th>Last login</th><th></th></tr></thead><tbody>
<?php foreach ($users as $u): ?>
<tr class="<?= $u['is_active'] ? '' : 'text-secondary' ?>"><td class="fw-semibold"><?= esc($u['fullname']) ?> <?= $u['is_superadmin'] ? '<span class="badge bg-success">super admin</span>' : '' ?><?= $u['is_active'] ? '' : ' <span class="badge bg-secondary">inactive</span>' ?></td>
 <td class="small"><?= esc($u['username']) ?><?= $u['email'] ? '<br>' . esc($u['email']) : '' ?><?= $u['moodle_userid'] ? '<br><span class="text-secondary">id ' . $u['moodle_userid'] . '</span>' : '' ?></td>
 <td class="small"><?= $u['is_superadmin'] ? '<em>all areas</em>' : esc(implode(', ', array_map(fn ($a) => $areas[$a]['name'] ?? $a, $userAreas[$u['id']] ?? []))) ?></td>
 <td class="small"><?= fmt_date($u['last_login_at'], 'M j, Y g:ia') ?></td>
 <td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#uModal" onclick='editU(<?= json_encode($u + ['areas' => $userAreas[$u['id']] ?? []]) ?>)'><i class="bi bi-pencil"></i></button>
  <?php if ($u['id'] !== Auth::id()): ?><form class="d-inline" method="post" action="<?= site_url('admin/users/' . $u['id'] . '/delete') ?>" onsubmit="return confirm('Remove access for this person?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
<div class="modal fade" id="uModal"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= site_url('admin/users') ?>" id="uForm"><?= csrf_field() ?><input type="hidden" name="id">
 <div class="modal-header"><h5 class="modal-title">Site access</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body">
  <div class="mb-2"><label class="form-label">Full name</label><input class="form-control" name="fullname" required></div>
  <div class="row g-2 mb-2"><div class="col-6"><label class="form-label">Moodle username</label><input class="form-control" name="username"></div><div class="col-6"><label class="form-label">Moodle email</label><input class="form-control" name="email"></div></div>
  <div class="mb-3"><label class="form-label">Moodle user id (optional)</label><input class="form-control" name="moodle_userid"></div>
  <label class="form-label">Areas</label>
  <?php foreach ($areas as $k => $a): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="areas[]" value="<?= $k ?>" id="a_<?= $k ?>"><label class="form-check-label" for="a_<?= $k ?>"><b><?= esc($a['name']) ?></b> <span class="text-secondary small">— <?= esc($a['desc']) ?></span></label></div><?php endforeach ?>
  <hr>
  <?php if (Auth::isSuper()): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="is_superadmin" value="1" id="is_superadmin"><label class="form-check-label" for="is_superadmin">Super admin (every area, can grant super admin)</label></div><?php endif ?>
  <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked><label class="form-check-label" for="is_active">Active</label></div>
 </div><div class="modal-footer"><button class="btn btn-dark">Save</button></div></form></div></div>
<script>function editU(u){const f=document.getElementById('uForm');for(const k of ['id','fullname','username','email','moodle_userid'])f.elements[k].value=u?(u[k]??''):'';
 f.querySelectorAll('input[name="areas[]"]').forEach(c=>c.checked=!!(u&&u.areas.includes(c.value)));
 if(f.elements['is_superadmin'])f.elements['is_superadmin'].checked=!!(u&&+u.is_superadmin);f.elements['is_active'].checked=u?!!+u.is_active:true;}</script>
<?= $this->endSection() ?>
