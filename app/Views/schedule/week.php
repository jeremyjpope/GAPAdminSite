<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-dark" href="<?= site_url('schedule/entries/new?date=' . $mon) ?>"><i class="bi bi-plus-lg"></i> Add session</a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= $this->include('schedule/_tabs') ?>
<?php $prev = date('Y-m-d', strtotime($mon . ' -7 days')); $next = date('Y-m-d', strtotime($mon . ' +7 days')); $today = date('Y-m-d'); ?>
<div class="d-flex align-items-center gap-2 mb-2 d-print-none">
 <a class="btn btn-sm btn-outline-dark" href="<?= site_url('schedule?date=' . $prev) ?>"><i class="bi bi-chevron-left"></i></a>
 <a class="btn btn-sm btn-outline-dark" href="<?= site_url('schedule') ?>">Today</a>
 <a class="btn btn-sm btn-outline-dark" href="<?= site_url('schedule?date=' . $next) ?>"><i class="bi bi-chevron-right"></i></a>
 <form class="d-flex gap-1" method="get"><input class="form-control form-control-sm" type="date" name="date" value="<?= $mon ?>" onchange="this.form.submit()"></form>
 <span class="ms-2 fw-semibold"><?= fmt_date($mon, 'M j') ?> – <?= fmt_date($days[6], 'M j, Y') ?></span>
 <?php if ($week): ?><span class="badge text-bg-light">GAP week <?= $week ?></span><?php endif ?>
 <?php foreach ($blocks as $b): ?><span class="badge" style="background:<?= esc($b['color'] ?: '#6c757d') ?>"><?= esc($b['label']) ?></span><?php endforeach ?>
 <a class="btn btn-sm btn-outline-secondary ms-auto" href="#" onclick="window.print();return false"><i class="bi bi-printer"></i></a>
</div>
<?php if (! $slots): ?><div class="alert alert-warning">No time slots are defined for <?= esc($year['label']) ?> yet. <a href="<?= site_url('schedule/settings') ?>">Set them up</a> (or copy last year's).</div><?php endif ?>
<style>
 .wk th, .wk td { vertical-align: top; }
 .wk td.day { min-width: 10rem; }
 .wk .today { background: #fffbe6; }
 .wk .slotcell { position: relative; }
 .wk .addbtn { position: absolute; right: .2rem; bottom: .1rem; opacity: 0; font-size: .8rem; }
 .wk .slotcell:hover .addbtn { opacity: .7; }
 .wk .aud-guys { background: #eef4fb; } .wk .aud-girls { background: #fcefef; }
 @media print { .sidebar, .navbar, .btn { display: none !important; } }
</style>
<div class="table-responsive"><table class="table table-bordered table-sm wk mb-0 bg-white">
 <thead class="table-light"><tr><th style="width:9rem">Slot</th>
  <?php foreach ($days as $d): ?><th class="day <?= $d === $today ? 'today' : '' ?>"><?= fmt_date($d, 'D') ?> <span class="fw-normal text-secondary"><?= fmt_date($d, 'M j') ?></span></th><?php endforeach ?></tr></thead>
 <tbody>
 <?php if (array_filter(array_map(fn ($d) => $allDay[$d] ?? [], $days))): ?>
 <tr><th class="small text-secondary">All day</th>
  <?php foreach ($days as $d): ?><td class="slotcell <?= $d === $today ? 'today' : '' ?>"><?php foreach ($allDay[$d] ?? [] as $e) echo view('schedule/_entry', ['e' => $e, 'courses' => $courses, 'staff' => $staff, 'slots' => $slots]); ?></td><?php endforeach ?></tr>
 <?php endif ?>
 <?php foreach ($slots as $s): ?>
 <tr><th class="small aud-<?= $s['audience'] ?>"><?= esc($s['label']) ?><div class="fw-normal text-secondary"><?= esc(slot_time($s)) ?></div></th>
  <?php foreach ($days as $d): ?><td class="slotcell aud-<?= $s['audience'] ?> <?= $d === $today ? 'today' : '' ?>">
   <?php foreach ($grid[$d][$s['id']] ?? [] as $e) echo view('schedule/_entry', ['e' => $e, 'courses' => $courses, 'staff' => $staff, 'slots' => $slots]); ?>
   <a class="addbtn text-decoration-none d-print-none" href="<?= site_url('schedule/entries/new?date=' . $d . '&slot=' . $s['id']) ?>" title="Add"><i class="bi bi-plus-circle"></i></a>
  </td><?php endforeach ?></tr>
 <?php endforeach ?>
 </tbody></table></div>
<p class="form-text mt-2">⇥ means the session runs on into the next slot. Blue rows are guys-only slots, pink rows girls-only. Click a session to edit it; hover a cell to add one.</p>
<?= $this->endSection() ?>
