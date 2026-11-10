<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $s = $student ?? []; $v = fn ($k) => esc(old($k, $s[$k] ?? '')); ?>
<form method="post" action="<?= $s ? site_url('students/' . $s['id']) : site_url('students') ?>"><?= csrf_field() ?>
<div class="row g-3">
 <div class="col-lg-8">
  <div class="card mb-3"><div class="card-header bg-white fw-semibold">Identity</div><div class="card-body row g-3">
    <div class="col-md-4"><label class="form-label">First name</label><input class="form-control" name="first_name" value="<?= $v('first_name') ?>" required></div>
    <div class="col-md-4"><label class="form-label">Last name</label><input class="form-control" name="last_name" value="<?= $v('last_name') ?>" required></div>
    <div class="col-md-4"><label class="form-label">Preferred name</label><input class="form-control" name="preferred_name" value="<?= $v('preferred_name') ?>"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= $v('email') ?>"></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= $v('phone') ?>"></div>
    <div class="col-md-2"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="">—</option><?php foreach (['M' => 'Male', 'F' => 'Female'] as $k => $l): ?><option value="<?= $k ?>" <?= ($s['gender'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
    <div class="col-md-2"><label class="form-label">Birthday</label><input class="form-control" type="date" name="birthday" value="<?= $v('birthday') ?>"></div>
    <div class="col-12"><label class="form-label">Home address</label><input class="form-control" name="address" value="<?= $v('address') ?>"></div>
  </div></div>
  <div class="card mb-3"><div class="card-header bg-white fw-semibold">Program details</div><div class="card-body row g-3">
    <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['enrolled', 'graduated', 'withdrawn'] as $o): ?><option <?= ($s['status'] ?? 'enrolled') === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach ?></select></div>
    <div class="col-md-3"><label class="form-label">Attended from</label><input class="form-control" name="attended_from" placeholder="August 2026" value="<?= $v('attended_from') ?>"></div>
    <div class="col-md-3"><label class="form-label">Attended to</label><input class="form-control" name="attended_to" placeholder="May 2027" value="<?= $v('attended_to') ?>"></div>
    <div class="col-md-3"><label class="form-label">Moodle user id</label><input class="form-control" name="moodle_userid" value="<?= $v('moodle_userid') ?>"></div>
    <div class="col-md-3"><label class="form-label">T-shirt</label><input class="form-control" name="tshirt_size" value="<?= $v('tshirt_size') ?>"></div>
    <div class="col-md-3"><label class="form-label">Placement preference</label><input class="form-control" name="placement_preference" placeholder="Preschool / Elementary" value="<?= $v('placement_preference') ?>"></div>
    <div class="col-md-3"><label class="form-label">Cleared to drive</label><input class="form-control" name="cleared_to_drive" value="<?= $v('cleared_to_drive') ?>"></div>
    <div class="col-md-3"><label class="form-label">Auto insurance</label><input class="form-control" name="auto_insurance" value="<?= $v('auto_insurance') ?>"></div>
    <div class="col-md-6"><label class="form-label">Housing preference</label><input class="form-control" name="housing_preference" value="<?= $v('housing_preference') ?>"></div>
    <div class="col-md-3"><label class="form-label">Host home</label><select class="form-select" name="host_home_id"><option value="">—</option><?php foreach ($homes as $h): ?><option value="<?= $h['id'] ?>" <?= ($s['host_home_id'] ?? '') == $h['id'] ? 'selected' : '' ?>><?= esc($h['family_name']) ?></option><?php endforeach ?></select></div>
    <div class="col-md-3"><label class="form-label">Mentor</label><select class="form-select" name="mentor_id"><option value="">—</option><?php foreach ($mentors as $m): ?><option value="<?= $m['id'] ?>" <?= ($s['mentor_id'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= esc($m['name']) ?></option><?php endforeach ?></select></div>
    <div class="col-12"><label class="form-label">Allergies / medical notes</label><textarea class="form-control" name="allergies_medical" rows="2"><?= $v('allergies_medical') ?></textarea></div>
    <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"><?= $v('notes') ?></textarea></div>
  </div></div>
  <button class="btn btn-dark">Save</button> <a class="btn btn-link" href="<?= $s ? site_url('students/' . $s['id']) : site_url('students') ?>">Cancel</a>
 </div>
</div>
</form>
<?= $this->endSection() ?>
