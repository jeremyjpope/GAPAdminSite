<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= $this->include('schedule/_tabs') ?>
<?php if (! $slots): ?><div class="alert alert-warning">No time slots are defined for <?= esc($year['label']) ?> yet. <a href="<?= site_url('schedule/settings') ?>">Set them up</a>.</div><?php endif ?>
<style>
 .ym { font-size: .78rem; } .ym th, .ym td { vertical-align: top; padding: .15rem .3rem; }
 .ym .blk { writing-mode: vertical-rl; transform: rotate(180deg); text-align: center; font-weight: 600; background: #f8f9fa; }
 .ym .wk { background: #f8f9fa; text-align: center; font-weight: 600; }
 .ym .weekend { background: #fafafa; color: #888; }
 .ym .aud-guys { background: #eef4fb; } .ym .aud-girls { background: #fcefef; }
 .ym .entry { margin-bottom: .1rem; }
 .ym .allday { background: #fff3cd; font-weight: 600; text-align: center; }
 .ym .wkstart td { border-top: 2px solid #999; }
 @media print { .sidebar, .navbar, .nav-pills { display: none !important; } .ym { font-size: .65rem; } }
</style>
<div class="d-flex justify-content-between align-items-center mb-2 d-print-none"><div class="small text-secondary"><?= count($days) ?> days · <?= fmt_date($days[0] ?? null) ?> – <?= fmt_date(end($days) ?: null) ?> · same layout as the Excel map, one row per day</div>
 <div><a class="btn btn-sm btn-outline-secondary" href="#" onclick="window.print();return false"><i class="bi bi-printer"></i> Print</a></div></div>
<?php
 $blockAt = function (string $d) use ($blocks) { foreach ($blocks as $b) { if ($d >= $b['start_date'] && $d <= $b['end_date']) return $b; } return null; };
 $slotIds = array_column($slots, 'id');
?>
<table class="table table-bordered ym mb-0 bg-white">
 <thead class="table-light sticky-top"><tr><th style="width:1.6rem">Block</th><th style="width:2rem">Wk</th><th style="width:5.5rem">Date</th>
  <?php foreach ($slots as $s): ?><th class="aud-<?= $s['audience'] ?>"><?= esc($s['label']) ?><div class="fw-normal text-secondary"><?= esc(slot_time($s)) ?></div></th><?php endforeach ?></tr></thead>
 <tbody>
 <?php $openBlock = null; $blockRows = 0; $openWeek = null;
 // pre-count rows per block and per week so we can rowspan
 $blockLen = []; $weekLen = [];
 foreach ($days as $d) { $b = $blockAt($d); $bk = $b ? $b['id'] : 'none' . substr($d, 0, 7); $blockLen[$bk] = ($blockLen[$bk] ?? 0) + 1; $w = gap_week($d, $year) ?? ('pre' . $d); $weekLen[$w] = ($weekLen[$w] ?? 0) + 1; }
 $seenBlock = []; $seenWeek = [];
 foreach ($days as $d):
  $b = $blockAt($d); $bk = $b ? $b['id'] : 'none' . substr($d, 0, 7); $w = gap_week($d, $year); $wk = $w ?? ('pre' . $d);
  $dow = (int) date('N', strtotime($d)); $weekend = $dow >= 6;
  $all = $allDay[$d] ?? [];
 ?>
 <tr class="<?= $dow === 1 ? 'wkstart' : '' ?> <?= $weekend ? 'weekend' : '' ?>">
  <?php if (! isset($seenBlock[$bk])): $seenBlock[$bk] = true; ?><td class="blk" rowspan="<?= $blockLen[$bk] ?>" style="<?= $b && $b['color'] ? 'background:' . esc($b['color']) : '' ?>"><?= esc($b['label'] ?? '') ?></td><?php endif ?>
  <?php if (! isset($seenWeek[$wk])): $seenWeek[$wk] = true; ?><td class="wk" rowspan="<?= $weekLen[$wk] ?>"><?= $w ?? '' ?></td><?php endif ?>
  <td class="text-nowrap"><a class="text-decoration-none text-body" href="<?= site_url('schedule?date=' . $d) ?>"><?= fmt_date($d, 'D m/d') ?></a></td>
  <?php if ($all): ?>
   <td class="allday" colspan="<?= count($slots) ?>"><?php foreach ($all as $e): $c = $courses[$e['course_id']] ?? null; ?><a class="text-decoration-none text-body" href="<?= site_url('schedule/entries/' . $e['id']) ?>"><?= esc($c['name'] ?? '?') ?><?= $e['title'] ? ' — ' . esc($e['title']) : '' ?></a><?= $e['start_date'] !== $d ? ' <span class="text-secondary fw-normal">(cont.)</span>' : '' ?> <?php endforeach ?></td>
  <?php else: $skip = 0; foreach ($slots as $i => $s): if ($skip > 0) { $skip--; continue; }
   $es = $grid[$d][$s['id']] ?? []; $span = 1; foreach ($es as $e) { $span = max($span, (int) $e['slot_span']); } $span = min($span, count($slots) - $i); $skip = $span - 1; ?>
   <td class="aud-<?= $s['audience'] ?>" <?= $span > 1 ? 'colspan="' . $span . '"' : '' ?>><?php foreach ($es as $e) echo view('schedule/_entry', ['e' => $e, 'courses' => $courses, 'staff' => $staff, 'slots' => $slots, 'compact' => true]); ?></td>
  <?php endforeach; endif ?>
 </tr>
 <?php endforeach ?>
 </tbody></table>
<?= $this->endSection() ?>
