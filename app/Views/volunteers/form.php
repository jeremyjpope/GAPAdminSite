<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $x = $v ?? []; $val = fn ($k) => esc(old($k, $x[$k] ?? '')); ?>
<form method="post" action="<?= $x ? site_url('volunteers/' . $x['id']) : site_url('volunteers') ?>" style="max-width:900px"><?= csrf_field() ?>
 <div class="card mb-3"><div class="card-body row g-3">
  <div class="col-md-4"><label class="form-label">First name</label><input class="form-control" name="first_name" value="<?= $val('first_name') ?>" required></div>
  <div class="col-md-4"><label class="form-label">Last name</label><input class="form-control" name="last_name" value="<?= $val('last_name') ?>" required></div>
  <div class="col-md-2"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="">—</option><option value="M" <?= ($x['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Male</option><option value="F" <?= ($x['gender'] ?? '') === 'F' ? 'selected' : '' ?>>Female</option></select></div>
  <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach ($cfg->statuses as $k => $l): ?><option value="<?= $k ?>" <?= ($x['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
  <div class="col-md-5"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= $val('email') ?>"></div>
  <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= $val('phone') ?>"></div>
  <div class="col-md-2"><label class="form-label">T-shirt</label><input class="form-control" name="tshirt_size" value="<?= $val('tshirt_size') ?>"></div>
  <div class="col-md-2"><label class="form-label">DBC member</label><input class="form-control" name="member_of_dbc" list="dbc" value="<?= $val('member_of_dbc') ?>"><datalist id="dbc"><option>Yes</option><option>No</option><option>Attendee</option><option>Lapsed</option></datalist></div>
  <div class="col-md-7"><label class="form-label">Street address</label><input class="form-control" name="address" value="<?= $val('address') ?>"></div>
  <div class="col-md-5"><label class="form-label">City, State ZIP</label><input class="form-control" name="city_state_zip" value="<?= $val('city_state_zip') ?>"></div>
  <div class="col-md-3"><label class="form-label">MP group placed on</label><input class="form-control" type="date" name="mp_group_placed_on" value="<?= $val('mp_group_placed_on') ?>"></div>
  <div class="col-md-5"><label class="form-label">Opportunity response</label><input class="form-control" name="opportunity_response" value="<?= $val('opportunity_response') ?>"></div>
  <div class="col-md-2"><label class="form-label">Driver cleared (year)</label><input class="form-control" type="number" name="driver_cleared_year" value="<?= $val('driver_cleared_year') ?>"></div>
  <div class="col-md-3"><label class="form-label">Background check</label><input class="form-control" name="background_check" value="<?= $val('background_check') ?>" placeholder="Yes / date"></div>
  <div class="col-md-3"><label class="form-label">SAAT training</label><input class="form-control" name="saat_training" value="<?= $val('saat_training') ?>" placeholder="Yes / expires…"></div>
  <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"><?= $val('notes') ?></textarea></div>
 </div></div>
 <?php if (! $x): ?>
 <div class="card mb-3"><div class="card-header bg-white fw-semibold">Roles</div><div class="card-body d-flex flex-wrap gap-3">
  <?php foreach ($cfg->roles as $r): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="<?= esc($r) ?>" id="nr_<?= md5($r) ?>" <?= $preRole === $r ? 'checked' : '' ?>><label class="form-check-label" for="nr_<?= md5($r) ?>"><?= esc($r) ?></label></div><?php endforeach ?>
 </div></div>
 <?php endif ?>
 <button class="btn btn-dark">Save</button> <a class="btn btn-link" href="<?= $x ? site_url('volunteers/' . $x['id']) : site_url('volunteers') ?>">Cancel</a>
</form>
<?= $this->endSection() ?>
