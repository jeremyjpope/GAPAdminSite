<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php use App\Libraries\Auth; ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Students · <?= esc($year['label'] ?? '') ?></div><div class="stat"><?= count($students) ?></div></div></div></div>
  <?php if (Auth::can('tuition')): ?>
  <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Tuition collected</div><div class="stat"><?= money($tuitionPaid) ?></div><div class="small text-secondary">of <?= money($tuitionDue) ?> due · <?= money($tuitionDue - $tuitionPaid) ?> outstanding</div></div></div></div>
  <?php endif ?>
  <?php if (Auth::can('housing')): ?>
  <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Host homes</div><div class="stat"><?= $hostHomes ?></div><div class="small text-secondary"><?= $unhoused ?> student(s) without a home</div></div></div></div>
  <?php endif ?>
  <?php if (Auth::can('mentors')): ?>
  <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Mentors</div><div class="stat"><?= $mentors ?></div><div class="small text-secondary"><?= $unmentored ?> student(s) without a mentor</div></div></div></div>
  <?php endif ?>
</div>
<?php if (Auth::can('schedule')): ?>
<div class="card mb-3"><div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">This week</span><a class="small" href="<?= site_url('schedule?date=' . $weekMon) ?>">Open the schedule</a></div>
 <div class="card-body py-2">
 <?php if (! $week): ?><span class="text-secondary small">Nothing on the schedule for <?= fmt_date($weekMon, 'M j') ?> – <?= fmt_date(date('Y-m-d', strtotime($weekMon . ' +6 days')), 'M j') ?>.</span><?php else: ?>
 <div class="row g-2 small">
 <?php for ($i = 0; $i < 7; $i++): $d = date('Y-m-d', strtotime($weekMon . " +{$i} days")); $es = array_filter($week, fn ($e) => $e['start_date'] <= $d && $e['end_date'] >= $d); if (! $es) continue; ?>
  <div class="col-md-6 col-xl"><div class="fw-semibold <?= $d === date('Y-m-d') ? 'text-primary' : '' ?>"><?= fmt_date($d, 'D M j') ?></div>
  <?php foreach ($es as $e): $c = $courses[$e['course_id']] ?? null; $s = $slots[$e['slot_id']] ?? null; ?>
   <div class="text-truncate"><span class="d-inline-block rounded-circle me-1" style="width:.5rem;height:.5rem;background:<?= $c ? course_color($c) : '#999' ?>"></span><span class="text-secondary"><?= $s ? esc(explode('–', slot_time($s))[0]) : 'all day' ?></span> <a class="text-decoration-none text-body" href="<?= site_url('schedule/entries/' . $e['id']) ?>"><?= esc($c['name'] ?? '?') ?><?= $e['title'] ? ': ' . esc($e['title']) : '' ?></a><?= $e['group_label'] ? ' <span class="badge text-bg-light border py-0">' . esc($e['group_label']) . '</span>' : '' ?></div>
  <?php endforeach ?></div>
 <?php endfor ?>
 </div><?php endif ?>
 </div></div>
<?php endif ?>
<div class="row g-3">
  <?php if (Auth::can('students')): ?>
  <div class="col-lg-7"><div class="card"><div class="card-header bg-white fw-semibold">Students</div>
    <div class="card-body p-0"><?php if (! $students): ?><div class="p-3 text-secondary">No students yet. <a href="<?= site_url('students/sync-moodle') ?>">Sync from Moodle</a> or <a href="<?= site_url('students/new') ?>">add one</a>.</div><?php else: ?>
    <div class="d-flex flex-wrap gap-2 p-3">
      <?php foreach ($students as $s): ?>
      <a href="<?= site_url('students/' . $s['id']) ?>" class="text-decoration-none text-dark text-center" style="width:88px">
        <?php if ($s['photo_path']): ?><img class="avatar-lg" style="width:72px;height:90px" src="<?= site_url('photos/' . $s['id']) ?>" alt=""><?php else: ?><div class="avatar-lg d-flex align-items-center justify-content-center text-secondary" style="width:72px;height:90px;margin:auto"><i class="bi bi-person fs-2"></i></div><?php endif ?>
        <div class="small mt-1 text-truncate"><?= esc($s['preferred_name'] ?: $s['first_name']) ?><br><?= esc($s['last_name']) ?></div>
      </a>
      <?php endforeach ?>
    </div><?php endif ?></div></div></div>
  <?php endif ?>
  <?php if (Auth::can('tuition')): ?>
  <div class="col-lg-5"><div class="card"><div class="card-header bg-white fw-semibold">Recent payments</div>
    <table class="table table-sm mb-0"><tbody>
    <?php if (! $recent): ?><tr><td class="text-secondary p-3">No payments recorded yet.</td></tr><?php endif ?>
    <?php foreach ($recent as $p): $s = $byId[$p['student_id']] ?? null; ?>
      <tr><td><?= fmt_date($p['paid_on']) ?></td><td><?= $s ? '<a href="' . site_url('tuition/' . $s['id']) . '">' . esc(student_name($s)) . '</a>' : '' ?></td><td class="text-end"><?= money($p['amount']) ?></td><td class="text-secondary small"><?= esc($p['method']) ?> <?= esc($p['reference']) ?></td></tr>
    <?php endforeach ?>
    </tbody></table></div></div>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
