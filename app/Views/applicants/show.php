<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?>
<?php if ($a['mp_link']): ?><a class="btn btn-sm btn-success" href="<?= esc($a['mp_link']) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Application in MP</a> <?php endif ?>
<a class="btn btn-sm btn-outline-dark" href="<?= site_url('applicants/' . $a['id'] . '/edit') ?>"><i class="bi bi-pencil"></i> Edit</a>
<?php if ($a['student_id']): ?><a class="btn btn-sm btn-dark" href="<?= site_url('students/' . $a['student_id']) ?>"><i class="bi bi-mortarboard"></i> Student record</a>
<?php else: ?><form class="d-inline" method="post" action="<?= site_url('applicants/' . $a['id'] . '/accept') ?>" onsubmit="return confirm('Accept <?= esc($a['first_name'] . ' ' . $a['last_name'], 'js') ?> into <?= esc($yearRow['label'] ?? '', 'js') ?>? This creates the student record.')"><?= csrf_field() ?><button class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i> Accept into GAP</button></form><?php endif ?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php [$sl, $sc] = $cfg->statuses[$a['status']] ?? [$a['status'], 'light']; ?>
<div class="row g-3">
 <div class="col-lg-7">
  <div class="card mb-3"><div class="card-body">
   <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <span class="badge text-bg-<?= $sc ?> fs-6"><?= esc($sl) ?></span>
    <span class="badge rounded-pill text-bg-<?= $a['gender'] === 'F' ? 'danger' : 'primary' ?>"><?= $a['gender'] === 'F' ? 'Girl' : ($a['gender'] === 'M' ? 'Guy' : '?') ?></span>
    <span class="text-secondary small"><?= esc($years[$a['program_year_id']] ?? '') ?><?= $a['submission_order'] ? ' · application #' . $a['submission_order'] : '' ?></span>
    <?php if ($a['status'] !== 'accepted'): ?>
    <form class="ms-auto d-flex gap-1" method="post" action="<?= site_url('applicants/' . $a['id'] . '/status') ?>"><?= csrf_field() ?>
     <select class="form-select form-select-sm" name="status"><?php foreach ($cfg->statuses as $k => [$l]): if ($k === 'accepted') continue; ?><option value="<?= $k ?>" <?= $a['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select>
     <button class="btn btn-sm btn-outline-dark">Set</button></form>
    <?php endif ?>
   </div>
   <dl class="row mb-0 small">
    <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= $a['email'] ? '<a href="mailto:' . esc($a['email']) . '">' . esc($a['email']) . '</a>' : '—' ?></dd>
    <dt class="col-sm-3">Phone</dt><dd class="col-sm-9"><?= esc($a['phone'] ?? '—') ?></dd>
    <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= esc($a['address'] ?? '—') ?></dd>
    <dt class="col-sm-3">Birthday</dt><dd class="col-sm-9"><?= $a['birthday'] ? fmt_date($a['birthday']) . ' <span class="text-secondary">(' . (new DateTime($a['birthday']))->diff(new DateTime(($yearRow['start_date'] ?? date('Y')) . ''))->y . ' at the start of the year)</span>' : '—' ?></dd>
    <dt class="col-sm-3">Parents</dt><dd class="col-sm-9"><?= $a['mom_email'] ? 'Mom: ' . esc($a['mom_email']) : '' ?><?= $a['mom_email'] && $a['dad_email'] ? ' · ' : '' ?><?= $a['dad_email'] ? 'Dad: ' . esc($a['dad_email']) : '' ?><?= ! $a['mom_email'] && ! $a['dad_email'] ? '—' : '' ?></dd>
    <dt class="col-sm-3">Housing</dt><dd class="col-sm-9"><?= esc($a['housing_preference'] ?? '—') ?></dd>
    <dt class="col-sm-3">Tuition</dt><dd class="col-sm-9"><?= esc($a['tuition_note'] ?? '—') ?></dd>
    <dt class="col-sm-3">T-shirt / placement</dt><dd class="col-sm-9"><?= esc(trim(($a['tshirt_size'] ?? '') . ' · ' . ($a['placement_preference'] ?? ''), ' ·') ?: '—') ?></dd>
    <dt class="col-sm-3">Medical</dt><dd class="col-sm-9"><?= esc($a['allergies_medical'] ?? '—') ?></dd>
    <dt class="col-sm-3">Driving</dt><dd class="col-sm-9"><?= esc(trim(($a['cleared_to_drive'] ?? '') . ' · ' . ($a['auto_insurance'] ?? ''), ' ·') ?: '—') ?></dd>
    <?php if ($a['notes']): ?><dt class="col-sm-3">Notes</dt><dd class="col-sm-9"><?= nl2br(esc($a['notes'])) ?></dd><?php endif ?>
    <?php if ($prior): ?><dt class="col-sm-3">Applied before</dt><dd class="col-sm-9"><a href="<?= site_url('applicants/' . $prior['id']) ?>"><?= esc($years[$prior['program_year_id']] ?? '') ?></a> (<?= esc($cfg->statuses[$prior['status']][0] ?? $prior['status']) ?>)</dd><?php endif ?>
    <?php if ($later): ?><dt class="col-sm-3">Applied again</dt><dd class="col-sm-9"><?php foreach ($later as $l): ?><a href="<?= site_url('applicants/' . $l['id']) ?>"><?= esc($years[$l['program_year_id']] ?? '') ?></a> <?php endforeach ?></dd><?php endif ?>
   </dl>
  </div></div>

  <div class="card mb-3" id="checklist"><div class="card-header bg-white fw-semibold">Checklist</div>
   <form method="post" action="<?= site_url('applicants/' . $a['id'] . '/steps') ?>"><?= csrf_field() ?>
   <table class="table table-sm mb-0 align-middle"><tbody>
   <?php foreach ($cfg->steps as $k => $label): $s = $steps[$k] ?? null; ?>
    <tr class="<?= $s && $s['done'] ? 'table-success' : '' ?>">
     <td style="width:1.5rem"><input class="form-check-input" type="checkbox" name="steps[<?= $k ?>][done]" value="1" <?= $s && $s['done'] ? 'checked' : '' ?>></td>
     <td style="width:14rem" class="small"><?= esc($label) ?></td>
     <td style="width:9.5rem"><input class="form-control form-control-sm" type="date" name="steps[<?= $k ?>][done_on]" value="<?= esc($s['done_on'] ?? '') ?>"></td>
     <td><input class="form-control form-control-sm" name="steps[<?= $k ?>][note]" value="<?= esc($s['note'] ?? '') ?>" placeholder="who, when, follow-ups…"></td>
    </tr>
   <?php endforeach ?>
   </tbody></table>
   <div class="card-footer bg-white"><button class="btn btn-sm btn-dark">Save checklist</button> <span class="small text-secondary ms-2">Setting a date ticks the step. Longer history goes in the notes on the right.</span></div>
   </form></div>
 </div>
 <div class="col-lg-5">
  <div class="card mb-3" id="rubric"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Interview rubric</span><?php if ($avg): ?><span class="badge text-bg-dark fs-6"><?= number_format($avg['total'], 2) ?></span><?php endif ?></div>
   <form class="card-body py-2" method="post" action="<?= site_url('applicants/' . $a['id'] . '/scores') ?>"><?= csrf_field() ?>
    <div class="small text-secondary mb-2">Your scores, 1–10. Leave a box empty to skip it; empty scores are not averaged.</div>
    <div class="row g-2">
    <?php foreach ($cfg->rubric as $k => [$l, $d]): ?>
     <div class="col-6"><label class="form-label small mb-0" title="<?= esc($d) ?>"><?= $l ?></label><select class="form-select form-select-sm" name="score[<?= $k ?>]"><option value="">—</option><?php for ($i = 1; $i <= 10; $i++): ?><option <?= ($mine[$k] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor ?></select></div>
    <?php endforeach ?>
    </div>
    <button class="btn btn-sm btn-dark mt-2">Save my scores</button>
   </form>
   <?php if ($scorers): ?>
   <table class="table table-sm mb-0 small"><thead class="table-light"><tr><th></th><?php foreach ($cfg->rubric as $k => [$l]): ?><th class="text-center"><?= $l ?></th><?php endforeach ?><th class="text-center">Avg</th></tr></thead><tbody>
   <?php foreach ($scorers as $uid => $u): $vals = []; foreach ($cfg->rubric as $k => $r) { if (isset($matrix[$k][$uid])) $vals[] = $matrix[$k][$uid]; } ?>
    <tr><td><?= esc(explode(' ', $u['fullname'])[0]) ?></td><?php foreach ($cfg->rubric as $k => $r): ?><td class="text-center"><?= $matrix[$k][$uid] ?? '<span class="text-secondary">–</span>' ?></td><?php endforeach ?><td class="text-center fw-semibold"><?= $vals ? number_format(array_sum($vals) / count($vals), 1) : '' ?></td></tr>
   <?php endforeach ?>
    <tr class="table-light fw-semibold"><td>Average</td><?php foreach ($cfg->rubric as $k => $r): ?><td class="text-center"><?= isset($avg['cats'][$k]) ? number_format($avg['cats'][$k], 1) : '' ?></td><?php endforeach ?><td class="text-center"><?= $avg ? number_format($avg['total'], 2) : '' ?></td></tr>
   </tbody></table>
   <?php endif ?>
  </div>
  <?= view('notes/_panel', ['type' => 'applicant', 'subjectId' => $a['id'], 'notes' => $notes, 'returnUrl' => site_url('applicants/' . $a['id']) . '#notes']) ?>
  <?php if (! $a['student_id']): ?><form class="mt-3 text-end" method="post" action="<?= site_url('applicants/' . $a['id'] . '/delete') ?>" onsubmit="return confirm('Delete this applicant and their notes?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete applicant</button></form><?php endif ?>
 </div>
</div>
<?= $this->endSection() ?>
