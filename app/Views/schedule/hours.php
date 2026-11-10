<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= $this->include('schedule/_tabs') ?>
<p class="small text-secondary">Hours = slot length × days, for every session that is not cancelled. Sessions in all-day blocks (trips, retreats) count 0 because they have no slot. Life Skills sessions roll up by category, everything else by pillar.</p>
<?php $h = fn ($v) => rtrim(rtrim(number_format($v, 1), '0'), '.'); ?>
<div class="row g-3">
<?php foreach ($byCat as $cat => $d): ?>
 <div class="col-md-6 col-xl-4"><div class="card h-100">
  <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold"><?= esc($cat) ?></span><span class="badge text-bg-dark"><?= $h($d['total']) ?> h</span></div>
  <div class="card-body small py-2">
   <?php arsort($d['groups']); foreach ($d['groups'] as $g => $v): ?><div class="d-flex justify-content-between"><span><?= esc($g) ?></span><span><?= $h($v) ?> h</span></div><?php endforeach ?>
   <?php if (count($d['courses']) > 1): ?><hr class="my-1"><?php arsort($d['courses']); foreach ($d['courses'] as $n => $v): ?><div class="d-flex justify-content-between text-secondary"><span><?= esc($n) ?></span><span><?= $h($v) ?> h</span></div><?php endforeach; endif ?>
  </div></div></div>
<?php endforeach ?>
<?php if (! $byCat): ?><div class="col-12 text-secondary">Nothing scheduled yet.</div><?php endif ?>
</div>
<p class="form-text mt-3">"Guys A" means the guys' slot, group A. A group letter without a sex is a coed slot split into groups.</p>
<?= $this->endSection() ?>
