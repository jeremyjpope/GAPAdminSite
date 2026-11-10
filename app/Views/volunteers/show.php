<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('volunteers') ?>">All volunteers</a> <a class="btn btn-sm btn-dark" href="<?= site_url('volunteers/' . $v['id'] . '/edit') ?>"><i class="bi bi-pencil"></i> Edit</a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $sel = array_column($roles, null, 'role'); ?>
<div class="row g-3">
 <div class="col-lg-7">
  <div class="card mb-3"><div class="card-body"><dl class="row mb-0 small">
   <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge text-bg-<?= $v['status'] === 'active' ? 'success' : ($v['status'] === 'potential' ? 'warning' : 'secondary') ?>"><?= esc($cfg->statuses[$v['status']] ?? $v['status']) ?></span></dd>
   <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= esc($v['email']) ?></dd>
   <dt class="col-sm-3">Phone</dt><dd class="col-sm-9"><?= esc($v['phone']) ?></dd>
   <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= esc($v['address']) ?><?= $v['city_state_zip'] ? ', ' . esc($v['city_state_zip']) : '' ?></dd>
   <dt class="col-sm-3">Gender</dt><dd class="col-sm-9"><?= $v['gender'] === 'M' ? 'Male' : ($v['gender'] === 'F' ? 'Female' : '') ?></dd>
   <dt class="col-sm-3">DBC member</dt><dd class="col-sm-9"><?= esc($v['member_of_dbc']) ?></dd>
   <dt class="col-sm-3">T-shirt</dt><dd class="col-sm-9"><?= esc($v['tshirt_size']) ?></dd>
   <dt class="col-sm-3">MP group placed</dt><dd class="col-sm-9"><?= fmt_date($v['mp_group_placed_on']) ?></dd>
   <dt class="col-sm-3">Opportunity response</dt><dd class="col-sm-9"><?= esc($v['opportunity_response']) ?></dd>
   <dt class="col-sm-3">Driver cleared</dt><dd class="col-sm-9"><?= $v['driver_cleared_year'] ? 'Yes (' . $v['driver_cleared_year'] . ')' : '<span class="text-secondary">no record</span>' ?></dd>
   <dt class="col-sm-3">Background check</dt><dd class="col-sm-9"><?= esc($v['background_check']) ?></dd>
   <dt class="col-sm-3">SAAT training</dt><dd class="col-sm-9"><?= esc($v['saat_training']) ?></dd>
   <?php if ($v['notes']): ?><dt class="col-sm-3">Notes</dt><dd class="col-sm-9"><?= nl2br(esc($v['notes'])) ?></dd><?php endif ?>
   <?php if ($homes): ?><dt class="col-sm-3">Host home</dt><dd class="col-sm-9"><?php foreach ($homes as $h): ?><a href="<?= site_url('housing') ?>"><?= esc($h['family_name']) ?></a> <?php endforeach ?></dd><?php endif ?>
   <?php if ($mentees): ?><dt class="col-sm-3">Mentoring</dt><dd class="col-sm-9"><?php foreach ($mentees as $s): ?><a class="badge text-bg-light text-decoration-none" href="<?= site_url('students/' . $s['id']) ?>"><?= esc(student_name($s)) ?></a> <?php endforeach ?></dd><?php endif ?>
  </dl></div></div>
  <?= view('notes/_panel', ['type' => 'volunteer', 'subjectId' => $v['id'], 'notes' => $notes, 'returnUrl' => site_url('volunteers/' . $v['id']) . '#notes']) ?>
 </div>
 <div class="col-lg-5">
  <div class="card"><div class="card-header bg-white fw-semibold">Roles</div>
   <form class="card-body" method="post" action="<?= site_url('volunteers/' . $v['id'] . '/roles') ?>"><?= csrf_field() ?>
    <?php foreach ($cfg->roles as $r): $on = isset($sel[$r]); $isLS = in_array($r, ['Life Skills', 'Fitness'], true); ?>
    <div class="form-check mb-1">
     <input class="form-check-input" type="checkbox" name="roles[]" value="<?= esc($r) ?>" id="r_<?= md5($r) ?>" <?= $on ? 'checked' : '' ?>>
     <label class="form-check-label" for="r_<?= md5($r) ?>"><?= esc($r) ?></label>
     <?php if ($isLS): ?><div class="d-flex gap-2 mt-1 mb-2">
      <input class="form-control form-control-sm" name="category[<?= esc($r) ?>]" list="lscats" placeholder="Category (Outdoors Guys…)" value="<?= esc($sel[$r]['category'] ?? '') ?>" style="max-width:220px">
      <div class="form-check"><input class="form-check-input" type="checkbox" name="lead[<?= esc($r) ?>]" value="1" id="l_<?= md5($r) ?>" <?= ! empty($sel[$r]['is_lead']) ? 'checked' : '' ?>><label class="form-check-label small" for="l_<?= md5($r) ?>">Lead</label></div>
     </div><?php endif ?>
    </div>
    <?php endforeach ?>
    <datalist id="lscats"><?php foreach ($cfg->lifeSkillsCategories as $c): ?><option value="<?= esc($c) ?>"><?php endforeach ?></datalist>
    <button class="btn btn-sm btn-dark mt-2">Save roles</button>
   </form>
  </div>
  <form class="mt-3 text-end" method="post" action="<?= site_url('volunteers/' . $v['id'] . '/delete') ?>" onsubmit="return confirm('Remove this volunteer and their roles and notes?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Remove volunteer</button></form>
 </div>
</div>
<?= $this->endSection() ?>
