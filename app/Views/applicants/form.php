<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $x = $a ?? []; $val = fn ($k) => esc(old($k, $x[$k] ?? '')); ?>
<form method="post" action="<?= $x ? site_url('applicants/' . $x['id']) : site_url('applicants') ?>" style="max-width:960px"><?= csrf_field() ?>
 <?php if (! $x): ?><p class="small text-secondary">Applications arrive in Ministry Platform; copy the details here and paste the MP link so the team can open the full application.</p><?php endif ?>
 <div class="card mb-3"><div class="card-header bg-white fw-semibold">Applicant</div><div class="card-body row g-3">
  <div class="col-md-1"><label class="form-label">#</label><input class="form-control" type="number" name="submission_order" value="<?= $val('submission_order') ?>"></div>
  <div class="col-md-3"><label class="form-label">First name</label><input class="form-control" name="first_name" value="<?= $val('first_name') ?>" required></div>
  <div class="col-md-2"><label class="form-label">Goes by</label><input class="form-control" name="preferred_name" value="<?= $val('preferred_name') ?>"></div>
  <div class="col-md-3"><label class="form-label">Last name</label><input class="form-control" name="last_name" value="<?= $val('last_name') ?>" required></div>
  <div class="col-md-3"><label class="form-label">Guy / girl</label><select class="form-select" name="gender"><option value="">—</option><option value="M" <?= ($x['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Guy</option><option value="F" <?= ($x['gender'] ?? '') === 'F' ? 'selected' : '' ?>>Girl</option></select></div>
  <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= $val('email') ?>"></div>
  <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= $val('phone') ?>"></div>
  <div class="col-md-3"><label class="form-label">Birthday</label><input class="form-control" type="date" name="birthday" value="<?= $val('birthday') ?>"></div>
  <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach ($cfg->statuses as $k => [$l]): if ($k === 'accepted' && ($x['status'] ?? '') !== 'accepted') continue; ?><option value="<?= $k ?>" <?= ($x['status'] ?? 'applied') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
  <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= $val('address') ?>"></div>
  <div class="col-md-4"><label class="form-label">Mom's email</label><input class="form-control" name="mom_email" value="<?= $val('mom_email') ?>"></div>
  <div class="col-md-4"><label class="form-label">Dad's email</label><input class="form-control" name="dad_email" value="<?= $val('dad_email') ?>"></div>
  <div class="col-md-4"><label class="form-label">Application in Ministry Platform</label><input class="form-control" name="mp_link" value="<?= $val('mp_link') ?>" placeholder="https://…"></div>
 </div></div>
 <div class="card mb-3"><div class="card-header bg-white fw-semibold">Details <span class="text-secondary fw-normal small">copied onto the student record on acceptance</span></div><div class="card-body row g-3">
  <div class="col-md-3"><label class="form-label">T-shirt size</label><input class="form-control" name="tshirt_size" value="<?= $val('tshirt_size') ?>"></div>
  <div class="col-md-5"><label class="form-label">Preschool / elementary preference</label><input class="form-control" name="placement_preference" value="<?= $val('placement_preference') ?>"></div>
  <div class="col-md-4"><label class="form-label">Tuition</label><input class="form-control" name="tuition_note" value="<?= $val('tuition_note') ?>" placeholder="4000 · waitlist · n/a"></div>
  <div class="col-md-6"><label class="form-label">Allergies / medical</label><input class="form-control" name="allergies_medical" value="<?= $val('allergies_medical') ?>"></div>
  <div class="col-md-3"><label class="form-label">Cleared to drive</label><input class="form-control" name="cleared_to_drive" value="<?= $val('cleared_to_drive') ?>" list="ctd"><datalist id="ctd"><option>Cleared to Drive</option><option>NOT CLEARED TO DRIVE</option></datalist></div>
  <div class="col-md-3"><label class="form-label">Auto insurance</label><input class="form-control" name="auto_insurance" value="<?= $val('auto_insurance') ?>"></div>
  <div class="col-12"><label class="form-label">Housing preference</label><input class="form-control" name="housing_preference" value="<?= $val('housing_preference') ?>" placeholder="Home · Host family · grandparents…"></div>
  <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= $val('notes') ?></textarea></div>
 </div></div>
 <button class="btn btn-dark">Save</button> <a class="btn btn-link" href="<?= $x ? site_url('applicants/' . $x['id']) : site_url('applicants') ?>">Cancel</a>
</form>
<?= $this->endSection() ?>
