<?= $this->extend('layouts/main') ?>
<?php use App\Libraries\Auth; ?>
<?= $this->section('actions') ?>
<div class="d-flex gap-2">
  <?php if (Auth::can('transcripts')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('transcripts/' . $s['id']) ?>"><i class="bi bi-file-earmark-text"></i> Transcript</a><?php endif ?>
  <?php if (Auth::can('tuition')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('tuition/' . $s['id']) ?>"><i class="bi bi-cash-coin"></i> Tuition</a><?php endif ?>
  <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('students/' . $s['id'] . '/grades') ?>"><i class="bi bi-mortarboard"></i> Moodle grades</a>
  <a class="btn btn-sm btn-dark" href="<?= site_url('students/' . $s['id'] . '/edit') ?>"><i class="bi bi-pencil"></i> Edit</a>
</div>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="row g-3">
 <div class="col-lg-3">
  <div class="card mb-3"><div class="card-body text-center">
    <?php if ($s['photo_path']): ?><img class="avatar-lg mb-2" src="<?= site_url('photos/' . $s['id']) ?>" alt=""><?php else: ?><div class="avatar-lg d-flex align-items-center justify-content-center text-secondary mx-auto mb-2"><i class="bi bi-person display-4"></i></div><?php endif ?>
    <form method="post" action="<?= site_url('students/' . $s['id'] . '/photo') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
      <input type="file" name="photo" accept="image/*" class="form-control form-control-sm mb-2" onchange="this.form.submit()">
    </form>
    <div class="fw-semibold"><?= esc(student_name($s)) ?></div>
    <span class="badge text-bg-<?= $s['status'] === 'enrolled' ? 'success' : 'secondary' ?>"><?= esc($s['status']) ?></span>
  </div></div>
  <?php if (Auth::can('tuition')): ?>
  <div class="card mb-3"><div class="card-body">
    <div class="text-secondary small">Tuition</div>
    <?php if ($acct): ?><div><?= money($paid) ?> paid of <?= money($acct['total_due']) ?></div><div class="small text-secondary"><?= esc($acct['plan_type']) ?> plan · <?= money($acct['total_due'] - $paid) ?> outstanding</div>
    <?php else: ?><div class="small text-secondary">No plan set. <a href="<?= site_url('tuition/' . $s['id']) ?>">Set one</a>.</div><?php endif ?>
  </div></div>
  <?php endif ?>
 </div>
 <div class="col-lg-9">
  <div class="card mb-3"><div class="card-body">
    <dl class="row mb-0 small">
      <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= esc($s['email']) ?></dd>
      <dt class="col-sm-3">Phone</dt><dd class="col-sm-9"><?= esc($s['phone']) ?></dd>
      <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= esc($s['address']) ?></dd>
      <dt class="col-sm-3">Birthday</dt><dd class="col-sm-9"><?= fmt_date($s['birthday']) ?></dd>
      <dt class="col-sm-3">Host home</dt><dd class="col-sm-9"><?= $home ? esc($home['family_name']) . ' <span class="text-secondary">· ' . esc($home['phone']) . ' · ' . esc($home['email']) . '</span>' : '<span class="text-secondary">none</span>' ?></dd>
      <dt class="col-sm-3">Mentor</dt><dd class="col-sm-9"><?= $mentor ? esc($mentor['name']) . ' <span class="text-secondary">· ' . esc($mentor['email']) . '</span>' : '<span class="text-secondary">none</span>' ?></dd>
      <dt class="col-sm-3">Placement</dt><dd class="col-sm-9"><?= esc($s['placement_preference']) ?></dd>
      <dt class="col-sm-3">T-shirt</dt><dd class="col-sm-9"><?= esc($s['tshirt_size']) ?></dd>
      <dt class="col-sm-3">Driving</dt><dd class="col-sm-9"><?= esc($s['cleared_to_drive']) ?> <?= $s['auto_insurance'] ? '· ' . esc($s['auto_insurance']) : '' ?></dd>
      <dt class="col-sm-3">Housing pref.</dt><dd class="col-sm-9"><?= esc($s['housing_preference']) ?></dd>
      <dt class="col-sm-3">Medical</dt><dd class="col-sm-9"><?= nl2br(esc($s['allergies_medical'])) ?></dd>
      <dt class="col-sm-3">Attended</dt><dd class="col-sm-9"><?= esc($s['attended_from']) ?><?= $s['attended_to'] ? ' – ' . esc($s['attended_to']) : '' ?></dd>
      <dt class="col-sm-3">Moodle</dt><dd class="col-sm-9"><?= $s['moodle_userid'] ? '<a href="' . esc($moodleUrl) . '/user/profile.php?id=' . (int) $s['moodle_userid'] . '" target="_blank">user ' . (int) $s['moodle_userid'] . '</a>' : '<span class="text-secondary">not linked</span>' ?></dd>
      <?php if ($s['notes']): ?><dt class="col-sm-3">Notes</dt><dd class="col-sm-9"><?= nl2br(esc($s['notes'])) ?></dd><?php endif ?>
    </dl>
  </div></div>

  <div class="row g-3">
   <div class="col-md-6"><div class="card"><div class="card-header bg-white fw-semibold">Parents &amp; contacts</div>
    <table class="table table-sm mb-0"><tbody>
    <?php foreach ($contacts as $c): ?><tr><td class="small"><span class="text-secondary"><?= esc($c['relation']) ?></span><br><?= esc($c['name']) ?></td><td class="small"><?= esc($c['email']) ?><br><?= esc($c['phone']) ?></td>
      <td class="text-end"><form method="post" action="<?= site_url('students/' . $s['id'] . '/contacts/' . $c['id'] . '/delete') ?>" onsubmit="return confirm('Remove contact?')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form></td></tr><?php endforeach ?>
    </tbody></table>
    <form class="card-body border-top row g-2" method="post" action="<?= site_url('students/' . $s['id'] . '/contacts') ?>"><?= csrf_field() ?>
      <div class="col-4"><input class="form-control form-control-sm" name="relation" placeholder="Mother" list="relations"><datalist id="relations"><option>Mother</option><option>Father</option><option>Guardian</option><option>Emergency</option></datalist></div>
      <div class="col-8"><input class="form-control form-control-sm" name="name" placeholder="Name" required></div>
      <div class="col-6"><input class="form-control form-control-sm" name="email" placeholder="Email"></div>
      <div class="col-4"><input class="form-control form-control-sm" name="phone" placeholder="Phone"></div>
      <div class="col-2"><button class="btn btn-sm btn-outline-dark w-100">Add</button></div>
    </form></div></div>
   <div class="col-md-6"><div class="card"><div class="card-header bg-white fw-semibold">Vehicles</div>
    <table class="table table-sm mb-0"><tbody>
    <?php foreach ($vehicles as $vh): ?><tr><td class="small"><?= esc($vh['description']) ?><br><span class="text-secondary font-monospace"><?= esc($vh['vin']) ?></span></td><td class="small"><?= esc($vh['oil_type']) ?><br><span class="text-secondary"><?= esc($vh['oil_notes']) ?></span></td>
      <td class="text-end"><form method="post" action="<?= site_url('students/' . $s['id'] . '/vehicles/' . $vh['id'] . '/delete') ?>" onsubmit="return confirm('Remove vehicle?')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form></td></tr><?php endforeach ?>
    </tbody></table>
    <form class="card-body border-top row g-2" method="post" action="<?= site_url('students/' . $s['id'] . '/vehicles') ?>"><?= csrf_field() ?>
      <div class="col-7"><input class="form-control form-control-sm" name="description" placeholder="2017 Honda Pilot" required></div>
      <div class="col-5"><input class="form-control form-control-sm" name="vin" placeholder="VIN"></div>
      <div class="col-5"><input class="form-control form-control-sm" name="oil_type" placeholder="Oil type"></div>
      <div class="col-5"><input class="form-control form-control-sm" name="oil_notes" placeholder="Filter / notes"></div>
      <div class="col-2"><button class="btn btn-sm btn-outline-dark w-100">Add</button></div>
    </form></div></div>
  </div>
 </div>
</div>
<?= $this->endSection() ?>
