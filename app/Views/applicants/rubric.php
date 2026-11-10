<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-outline-dark" href="<?= site_url('applicants') ?>">Applicants</a> <a class="btn btn-sm btn-outline-secondary" href="#" onclick="window.print();return false"><i class="bi bi-printer"></i></a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<style>.rb { font-size:.75rem } .rb td, .rb th { padding:.15rem .3rem; text-align:center; white-space:nowrap } .rb th.name, .rb td.name { text-align:left } .rb .cat { border-left: 2px solid #999 } .rb .tot { background:#d9ead3; font-weight:600 } .rb .acc td.name { background:#d9ead3 }</style>
<?php $cats = $cfg->rubric; $n = count($scorers); ?>
<?php foreach (['M' => 'Guy applicants', 'F' => 'Girl applicants'] as $g => $title): $list = array_filter($rows, fn ($r) => $r['gender'] === $g); if (! $list) continue; ?>
<div class="card mb-3"><div class="card-header bg-white fw-semibold"><?= $title ?></div>
<div class="table-responsive"><table class="table table-bordered rb mb-0">
 <thead class="table-light"><tr><th>#</th><th class="name">Applicant</th><?php foreach ($cats as $k => [$l]): ?><th colspan="<?= max(1, $n) ?>" class="cat"><?= $l ?></th><?php endforeach ?><th class="cat">Total</th></tr>
  <tr><th></th><th></th><?php foreach ($cats as $k => $r): $i = 0; foreach ($scorers as $u): ?><th class="fw-normal <?= $i++ === 0 ? 'cat' : '' ?>"><?= esc(explode(' ', $u['fullname'])[0]) ?></th><?php endforeach; if (! $n): ?><th class="cat"></th><?php endif; endforeach ?><th class="cat"></th></tr></thead>
 <tbody>
 <?php foreach ($list as $a): ?>
  <tr class="<?= $a['status'] === 'accepted' ? 'acc' : '' ?>"><td><?= $a['submission_order'] ?? '' ?></td><td class="name"><a class="text-decoration-none text-body" href="<?= site_url('applicants/' . $a['id']) ?>"><?= esc($a['first_name'] . ' ' . $a['last_name']) ?></a></td>
  <?php foreach ($cats as $k => $r): $i = 0; foreach ($scorers as $uid => $u): ?><td class="<?= $i++ === 0 ? 'cat' : '' ?>"><?= $matrix[$a['id']][$k][$uid] ?? '' ?></td><?php endforeach; if (! $n): ?><td class="cat"></td><?php endif; endforeach ?>
  <td class="tot cat"><?= isset($avg[$a['id']]) ? number_format($avg[$a['id']]['total'], 2) : '' ?></td></tr>
 <?php endforeach ?>
 </tbody></table></div></div>
<?php endforeach ?>
<?php if (! $rows): ?><div class="text-secondary">No applicants for <?= esc($year['label'] ?? '') ?>.</div><?php endif ?>
<div class="card"><div class="card-header bg-white fw-semibold">Key</div><div class="card-body small py-2">
<?php foreach ($cfg->rubric as $k => [$l, $d]): ?><div><b><?= $l ?>:</b> <?= esc($d) ?></div><?php endforeach ?>
<div class="text-secondary mt-1">Total = average of every score entered (empty cells are ignored). Green names are accepted. Each leadership-team member enters their own scores on the applicant's page.</div>
</div></div>
<?= $this->endSection() ?>
