<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-outline-dark" href="<?= site_url('applicants/rubric') ?>"><i class="bi bi-clipboard-data"></i> Interview rubric</a> <a class="btn btn-sm btn-dark" href="<?= site_url('applicants/new') ?>"><i class="bi bi-plus-lg"></i> New applicant</a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form class="row g-2 mb-3 align-items-center" method="get">
 <div class="col-auto"><input class="form-control form-control-sm" name="q" value="<?= esc($q) ?>" placeholder="Search all years by name or email"></div>
 <div class="col-auto"><select class="form-select form-select-sm" name="sex" onchange="this.form.submit()"><option value="">Guys & girls</option><option value="M" <?= $sex === 'M' ? 'selected' : '' ?>>Guys</option><option value="F" <?= $sex === 'F' ? 'selected' : '' ?>>Girls</option></select></div>
 <div class="col-auto"><button class="btn btn-sm btn-outline-dark">Search</button> <?php if ($q !== '' || $status !== '' || $sex !== ''): ?><a class="btn btn-sm btn-link" href="<?= site_url('applicants') ?>">clear</a><?php endif ?></div>
 <div class="col-auto ms-auto small">
  <a class="badge rounded-pill text-decoration-none text-bg-<?= $status === '' ? 'dark' : 'light' ?>" href="<?= site_url('applicants') ?>">All <?= array_sum($counts) ?></a>
  <?php foreach ($cfg->statuses as $k => [$l, $c]): if (empty($counts[$k])) continue; ?><a class="badge rounded-pill text-decoration-none text-bg-<?= $status === $k ? $c : 'light' ?>" href="<?= site_url('applicants?status=' . $k) ?>"><?= $l ?> <?= $counts[$k] ?></a> <?php endforeach ?>
 </div>
</form>
<?php $stepKeys = array_keys($cfg->steps); $short = ['applied' => 'App', 'preview' => 'Prev', 'video' => 'Vid', 'personal_ref' => 'Ref 1', 'spiritual_ref' => 'Ref 2', 'interview' => 'Int', 'decision' => 'Dec', 'commitment' => 'Com', 'background' => 'BGC', 'books' => 'Bks', 'canvas' => 'LMS', 'newsletter' => 'News', 'dates_doc' => 'Dates']; ?>
<div class="card"><table class="table table-sm table-hover mb-0 align-middle">
 <thead class="table-light"><tr><th>#</th><th>Applicant</th><?php if ($q !== ''): ?><th>Year</th><?php endif ?><th>Status</th><th class="small">Checklist</th><th class="text-end">Rubric</th><th>Contact</th><th>Housing</th><th></th></tr></thead>
 <tbody>
 <?php foreach ($rows as $a): $d = $done[$a['id']] ?? []; ?>
  <tr>
   <td class="text-secondary small"><?= $a['submission_order'] ?? '' ?></td>
   <td><a class="text-decoration-none fw-semibold <?= $a['status'] === 'accepted' ? 'text-success' : '' ?>" href="<?= site_url('applicants/' . $a['id']) ?>"><?= esc($a['first_name']) ?><?= $a['preferred_name'] ? ' (' . esc($a['preferred_name']) . ')' : '' ?> <?= esc($a['last_name']) ?></a>
    <span class="badge rounded-pill text-bg-<?= $a['gender'] === 'F' ? 'danger' : 'primary' ?> py-0"><?= $a['gender'] === 'F' ? 'girl' : 'guy' ?></span>
    <?php if (! empty($notes[$a['id']])): ?><span class="badge text-bg-light"><i class="bi bi-journal-text"></i> <?= $notes[$a['id']] ?></span><?php endif ?></td>
   <?php if ($q !== ''): ?><td class="small"><?= esc($years[$a['program_year_id']] ?? '') ?></td><?php endif ?>
   <td><span class="badge text-bg-<?= $cfg->statuses[$a['status']][1] ?? 'light' ?>"><?= esc($cfg->statuses[$a['status']][0] ?? $a['status']) ?></span></td>
   <td class="small text-nowrap"><?php foreach ($stepKeys as $k): if (in_array($k, ['newsletter', 'dates_doc', 'video', 'canvas'], true)) continue; ?><span class="badge <?= isset($d[$k]) ? 'text-bg-success' : 'text-bg-light border text-secondary' ?> py-0" title="<?= esc($cfg->steps[$k]) ?><?= isset($d[$k]) && $d[$k]['done_on'] ? ' ' . fmt_date($d[$k]['done_on']) : '' ?>"><?= $short[$k] ?></span> <?php endforeach ?></td>
   <td class="text-end small"><?= isset($avg[$a['id']]) ? '<span class="fw-semibold">' . number_format($avg[$a['id']]['total'], 1) . '</span> <span class="text-secondary">(' . $avg[$a['id']]['n'] . ')</span>' : '' ?></td>
   <td class="small text-secondary"><?= esc($a['email'] ?? '') ?><?= $a['phone'] ? '<br>' . esc($a['phone']) : '' ?></td>
   <td class="small text-secondary text-truncate" style="max-width:14rem" title="<?= esc($a['housing_preference'] ?? '') ?>"><?= esc($a['housing_preference'] ?? '') ?></td>
   <td class="text-end text-nowrap"><?php if ($a['student_id']): ?><a class="btn btn-sm btn-link p-0" href="<?= site_url('students/' . $a['student_id']) ?>" title="Student record"><i class="bi bi-mortarboard"></i></a><?php endif ?><?php if ($a['mp_link']): ?> <a class="btn btn-sm btn-link p-0 text-success" href="<?= esc($a['mp_link']) ?>" target="_blank" rel="noopener" title="Application in Ministry Platform"><i class="bi bi-box-arrow-up-right"></i></a><?php endif ?></td>
  </tr>
 <?php endforeach ?>
 <?php if (! $rows): ?><tr><td colspan="9" class="text-secondary p-3">No applicants<?= $q !== '' ? ' match' : ' for ' . esc($year['label'] ?? '') . ' yet' ?>.</td></tr><?php endif ?>
 </tbody></table></div>
<p class="form-text mt-2">Checklist badges turn green as steps are done (App = application, Ref 1/2 = personal/spiritual reference, Int = interview, Dec = decision sent, Com = commitment letter, BGC = background check/SAAT/MP, Bks = books). Rubric is the average of every score entered, with the number of scorers in brackets. A search covers every year.</p>
<?= $this->endSection() ?>
