<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?>
<div class="d-flex gap-2">
 <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('groups') ?>">All sets</a>
 <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('groups/sets/' . $set['id'] . '/print') ?>" target="_blank"><i class="bi bi-printer"></i> Print</a>
 <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#setModal" onclick='editSet(<?= json_encode($set) ?>)'><i class="bi bi-pencil"></i> Edit set</button>
 <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#groupModal" onclick="editGroup(null)"><i class="bi bi-plus-lg"></i> Add <?= esc(strtolower($set['group_label'])) ?></button>
</div>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php if ($set['notes']): ?><p class="text-secondary small"><?= nl2br(esc($set['notes'])) ?></p><?php endif ?>
<div class="row g-3">
 <div class="col-lg-7">
  <div class="row g-2">
  <?php foreach ($groups as $g): $ms = $members[$g['id']] ?? []; ?>
   <div class="col-sm-6 col-xl-4"><div class="card h-100">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-1">
     <span class="fw-semibold"><?= esc($set['group_label']) ?> <?= esc($g['name']) ?></span>
     <span class="text-nowrap"><button class="btn btn-sm btn-link text-secondary p-0 me-2" data-bs-toggle="modal" data-bs-target="#groupModal" onclick='editGroup(<?= json_encode($g) ?>)'><i class="bi bi-pencil"></i></button>
     <form class="d-inline" method="post" action="<?= site_url('groups/sets/' . $set['id'] . '/groups/' . $g['id'] . '/delete') ?>" onsubmit="return confirm('Remove this group? Its members become unassigned.')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form></span>
    </div>
    <ul class="list-group list-group-flush">
     <?php foreach ($ms as $s): ?><li class="list-group-item py-1 d-flex justify-content-between align-items-center"><a class="text-decoration-none" href="<?= site_url('students/' . $s['id']) ?>"><?= esc(student_name($s)) ?></a>
      <form method="post" action="<?= site_url('groups/sets/' . $set['id'] . '/assign') ?>"><?= csrf_field() ?><input type="hidden" name="student_id" value="<?= $s['id'] ?>"><input type="hidden" name="group_id" value="0"><button class="btn btn-sm btn-link text-secondary p-0" title="Unassign"><i class="bi bi-x"></i></button></form></li><?php endforeach ?>
     <?php if (! $ms): ?><li class="list-group-item py-1 text-secondary small">empty</li><?php endif ?>
    </ul>
    <?php if ($g['notes']): ?><div class="card-footer bg-white small text-secondary py-1"><?= esc($g['notes']) ?></div><?php endif ?>
   </div></div>
  <?php endforeach ?>
  <?php if (! $groups): ?><div class="col-12"><div class="card"><div class="card-body text-secondary">No <?= esc(strtolower($set['group_label'])) ?>s yet. Add one, then assign students on the right.</div></div></div><?php endif ?>
  </div>
 </div>
 <div class="col-lg-5"><div class="card"><div class="card-header bg-white fw-semibold">Assignments</div>
  <table class="table table-sm mb-0"><tbody>
  <?php foreach ($students as $s): ?>
  <tr class="<?= empty($assignment[$s['id']]) ? 'table-warning' : '' ?>"><td class="small"><?= esc(student_name($s)) ?></td><td>
   <form method="post" action="<?= site_url('groups/sets/' . $set['id'] . '/assign') ?>"><?= csrf_field() ?><input type="hidden" name="student_id" value="<?= $s['id'] ?>">
    <select class="form-select form-select-sm" name="group_id" onchange="this.form.submit()"><option value="0">— unassigned —</option>
    <?php foreach ($groups as $g): ?><option value="<?= $g['id'] ?>" <?= ($assignment[$s['id']] ?? 0) == $g['id'] ? 'selected' : '' ?>><?= esc($set['group_label']) ?> <?= esc($g['name']) ?></option><?php endforeach ?></select></form>
  </td></tr>
  <?php endforeach ?>
  </tbody></table></div></div>
</div>
<div class="modal fade" id="groupModal"><div class="modal-dialog modal-sm"><form class="modal-content" method="post" action="<?= site_url('groups/sets/' . $set['id'] . '/groups') ?>" id="groupForm"><?= csrf_field() ?><input type="hidden" name="id">
 <div class="modal-header"><h5 class="modal-title"><?= esc($set['group_label']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body row g-2">
  <div class="col-8"><label class="form-label">Name</label><input class="form-control" name="name" placeholder="1, A, Preschool…" required></div>
  <div class="col-4"><label class="form-label">Sort</label><input class="form-control" name="sort_order" type="number"></div>
  <div class="col-12"><label class="form-label">Notes</label><input class="form-control" name="notes" placeholder="leader, room, time…"></div>
 </div><div class="modal-footer"><button class="btn btn-dark">Save</button></div></form></div></div>
<script>function editGroup(g){const f=document.getElementById('groupForm');for(const k of ['id','name','sort_order','notes'])f.elements[k].value=g?(g[k]??''):'';}</script>
<?= $this->include('groups/_set_modal') ?>
<?= $this->endSection() ?>
