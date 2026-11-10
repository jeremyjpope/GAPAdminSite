<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= $this->include('schedule/_tabs') ?>
<form class="row g-2 align-items-end mb-3 d-print-none" method="get">
 <div class="col-auto"><label class="form-label small mb-0">From</label><input class="form-control form-control-sm" type="date" name="from" value="<?= esc($from) ?>"></div>
 <div class="col-auto form-check mb-1 ms-2"><input class="form-check-input" type="checkbox" name="all" value="1" id="all" <?= $all ? 'checked' : '' ?>><label class="form-check-label small" for="all">Whole year</label></div>
 <div class="col-auto"><button class="btn btn-sm btn-outline-dark">Show</button></div>
</form>
<?php foreach ($byPerson as $vid => $d): $p = $d['person']; ?>
<div class="card mb-2"><div class="card-header bg-white py-1 d-flex justify-content-between align-items-center">
 <span><a class="fw-semibold text-decoration-none" href="<?= site_url('volunteers/' . $vid) ?>"><?= esc($p['first_name'] . ' ' . $p['last_name']) ?></a> <span class="small text-secondary"><?= esc($p['email'] ?? '') ?> <?= esc($p['phone'] ?? '') ?></span></span>
 <span class="badge text-bg-light"><?= count($d['sessions']) ?></span></div>
 <table class="table table-sm mb-0 small"><tbody>
 <?php foreach ($d['sessions'] as $s): $sl = $slots[$s['slot_id']] ?? null; ?>
  <tr><td class="text-nowrap" style="width:9rem"><?= fmt_date($s['start_date'], 'D M j') ?><?= $s['end_date'] !== $s['start_date'] ? '–' . fmt_date($s['end_date'], 'M j') : '' ?></td>
   <td class="text-nowrap text-secondary" style="width:7rem"><?= $sl ? esc(slot_time($sl)) : 'all day' ?></td>
   <td><a class="text-decoration-none text-body" href="<?= site_url('schedule/entries/' . $s['id']) ?>"><?= esc($s['course_name']) ?><?= $s['title'] ? ' — ' . esc($s['title']) : '' ?></a> <?= $s['group_label'] ? '<span class="badge text-bg-light border">' . esc($s['group_label']) . '</span>' : '' ?> <?= $s['staff_role'] !== 'instructor' ? '<span class="badge text-bg-light">' . esc($cfg->staffRoles[$s['staff_role']] ?? $s['staff_role']) . '</span>' : '' ?></td>
   <td class="text-secondary"><?= esc($s['location'] ?? '') ?></td>
   <td><?php if ($s['status'] !== 'planned'): ?><span class="badge text-bg-<?= ['confirmed' => 'success', 'done' => 'secondary', 'missed' => 'danger', 'cancelled' => 'dark'][$s['status']] ?? 'light' ?>"><?= $s['status'] ?></span><?php endif ?></td></tr>
 <?php endforeach ?>
 </tbody></table></div>
<?php endforeach ?>
<?php if (! $byPerson): ?><div class="text-secondary">No sessions with assigned staff in this range.</div><?php endif ?>
<?= $this->endSection() ?>
