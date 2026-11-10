<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= $this->include('schedule/_tabs') ?>
<p class="small text-secondary">One row per course, one column per GAP week; a cell shows how many days that week the course meets. Set "GAP week 1 starts" on the program year if the columns read 0.</p>
<style>.yg { font-size:.75rem } .yg td, .yg th { padding:.1rem .25rem; text-align:center } .yg td.c { color:#fff; font-weight:600 } .yg th.name { text-align:left; white-space:nowrap }</style>
<div class="table-responsive"><table class="table table-bordered table-sm yg bg-white">
 <thead class="table-light"><tr><th class="name">Course</th><?php foreach ($weeks as $w): ?><th title="<?= esc($weekStarts[$w]) ?>"><?= $w ?: '–' ?></th><?php endforeach ?><th>Days</th></tr>
 <tr class="text-secondary fw-normal"><th class="name fw-normal">week of</th><?php foreach ($weeks as $w): ?><th class="fw-normal" style="font-size:.6rem"><?= esc($weekStarts[$w]) ?></th><?php endforeach ?><th></th></tr></thead>
 <tbody>
 <?php $lastPillar = null; foreach ($rows as $r): $c = $r['course']; if ($c['pillar'] !== $lastPillar): $lastPillar = $c['pillar']; ?>
  <tr class="table-light"><th class="name" colspan="<?= count($weeks) + 2 ?>"><?= esc($cfg->pillars[$c['pillar']][0] ?? $c['pillar']) ?></th></tr>
 <?php endif ?>
  <tr><th class="name fw-normal"><a class="text-decoration-none text-body" href="<?= site_url('courses/' . $c['id']) ?>"><?= esc($c['name']) ?></a></th>
  <?php foreach ($weeks as $w): $n = $r['weeks'][$w] ?? 0; ?><td class="<?= $n ? 'c' : '' ?>" style="<?= $n ? 'background:' . course_color($c) . ';opacity:' . min(1, .45 + $n * .15) : '' ?>"><?= $n ?: '' ?></td><?php endforeach ?>
  <td><?= $r['total'] ?></td></tr>
 <?php endforeach ?>
 <?php if (! $rows): ?><tr><td colspan="<?= count($weeks) + 2 ?>" class="text-secondary">Nothing scheduled yet.</td></tr><?php endif ?>
 </tbody></table></div>
<?= $this->endSection() ?>
