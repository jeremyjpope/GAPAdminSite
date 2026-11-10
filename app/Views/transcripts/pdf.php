<?php $scale = json_decode($year['grade_scale'] ?? '[]', true) ?: []; ?>
<!doctype html><html><head><meta charset="utf-8"><style>
 @page { margin: 40px 50px 36px; }
 body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
 .hdr { text-align:center; margin-bottom: 8px; }
 .hdr .logo { height: 46px; margin-bottom: 4px; }
 .hdr .prog { font-size: 11pt; letter-spacing: 3px; font-weight: bold; }
 .hdr .sub { color:#666; font-size: 9pt; }
 .title { text-align:center; letter-spacing: 4px; color:#5b6b3a; font-size: 12pt; font-weight: bold; margin: 6px 0 3px; }
 .rule { border-top: 2px solid #5b6b3a; margin-bottom: 8px; }
 table.meta { width:100%; margin-bottom: 8px; }
 table.meta td { padding: 1px 0; }
 .lbl { font-size: 7pt; letter-spacing: 1px; color:#777; text-transform: uppercase; padding-right: 6px; }
 table.courses { width:100%; border-collapse: collapse; }
 table.courses th { background:#2f2f2f; color:#fff; font-size: 7pt; letter-spacing: 1px; text-align:left; padding: 4px 6px; }
 table.courses td { padding: 2.5px 6px; border-bottom: 1px solid #e3e3e3; }
 table.courses td.desc { color:#666; font-size: 8pt; }
 table.courses tr.pillar td { background:#f0f0ec; color:#5b6b3a; font-weight:bold; letter-spacing: 1px; font-size: 7.5pt; text-transform: uppercase; border-bottom: 0; }
 .num { text-align:right; } .ctr { text-align:center; font-weight:bold; }
 table.sum { width:100%; margin-top: 10px; background:#f0f0ec; border-top: 2px solid #2f2f2f; }
 table.sum td { text-align:center; padding: 6px 4px; }
 table.sum .v { font-size: 13pt; font-weight: bold; }
 .notes { margin-top: 8px; font-size: 8pt; color:#333; } .notes p { margin: 4px 0; }
 table.sig { width:100%; margin-top: 34px; }
 table.sig td { width:50%; padding: 0 20px; vertical-align: bottom; }
 .line { border-top: 1px solid #333; padding-top: 4px; }
 .foot { position: fixed; bottom: -14px; left:0; right:0; text-align:center; font-size: 7.5pt; color:#888; }
</style></head><body>
<div class="hdr">
  <?php if ($logo): ?><img class="logo" src="<?= $logo ?>"><?php else: ?><div style="font-size:26pt;font-weight:bold;letter-spacing:4px">G|G</div><?php endif ?>
  <div class="prog">GOD'S ALTERNATIVE PROGRAM</div>
  <div class="sub">Denton Bible Church &middot; Denton, Texas</div>
</div>
<div class="title">OFFICIAL TRANSCRIPT</div>
<div class="rule"></div>
<table class="meta"><tr>
 <td style="width:50%"><table>
  <tr><td class="lbl">Student</td><td><b><?= esc($s['first_name'] . ' ' . $s['last_name']) ?></b></td></tr>
  <tr><td class="lbl">Attended</td><td><?= esc(trim(($s['attended_from'] ?? '') . ($s['attended_to'] ? ' – ' . $s['attended_to'] : ''))) ?></td></tr>
  <tr><td class="lbl">Program</td><td><?= esc($year['program_description']) ?></td></tr>
 </table></td>
 <td><table>
  <tr><td class="lbl">Program year</td><td><?= esc(preg_replace('/-(\d\d)$/', '–20$1', $year['label'])) ?></td></tr>
  <tr><td class="lbl">Status</td><td><?= esc(ucfirst($s['status'])) ?><?= $s['status'] === 'graduated' && $s['attended_to'] ? ', ' . esc($s['attended_to']) : '' ?></td></tr>
  <tr><td class="lbl">Date issued</td><td><?= esc($issued) ?></td></tr>
 </table></td>
</tr></table>
<table class="courses">
 <thead><tr><th style="width:26%">Course</th><th>Description</th><th style="width:13%">Term</th><th class="num" style="width:8%">Hours</th><th class="ctr" style="width:8%">Grade</th></tr></thead>
 <tbody>
 <?php $pillar = null; foreach ($courses as $c): $g = $grades[$c['id']] ?? null; if (! $g || ! $g['letter']) continue;
   if ($c['pillar'] !== $pillar): $pillar = $c['pillar']; ?><tr class="pillar"><td colspan="5"><?= esc($pillar) ?></td></tr><?php endif ?>
 <tr><td><b><?= esc($c['name']) ?></b></td><td class="desc"><?= esc($c['description']) ?></td><td><?= esc($c['term']) ?></td><td class="num"><?= $c['hours'] ?></td><td class="ctr"><?= esc($g['letter']) ?></td></tr>
 <?php endforeach ?>
 </tbody>
</table>
<table class="sum"><tr>
 <td><div class="lbl">Courses completed</div><div class="v"><?= $summary['courses'] ?></div></td>
 <td><div class="lbl">Instructional hours</div><div class="v"><?= $summary['hours'] ?></div></td>
 <td><div class="lbl">Cumulative GPA</div><div class="v"><?= $summary['gpa'] !== null ? number_format($summary['gpa'], 2) : '—' ?></div></td>
 <td><div class="lbl">Program status</div><div class="v"><?= esc(ucfirst($s['status'])) ?></div></td>
</tr></table>
<div class="notes">
 <?php if ($year['transcript_notes']): ?><p><?= esc($year['transcript_notes']) ?></p><?php endif ?>
 <p><b>Grading scale:</b>
 <?php $parts = []; $prevMin = 100; usort($scale, fn ($a, $b) => $b['min'] <=> $a['min']);
   foreach ($scale as $i => $r) { $parts[] = $r['letter'] . ' = ' . ($r['min'] > 0 ? $r['min'] . '–' . $prevMin : 'below ' . ($prevMin + 1)) . ' (' . number_format($r['points'], 1) . ')'; $prevMin = $r['min'] - 1; } ?>
 <?= esc(implode(' · ', $parts)) ?>. Hours are instructional contact hours.</p>
</div>
<table class="sig"><tr>
 <td><div class="line"><b>Jeremy Pope</b><br><span style="color:#666;font-size:8pt">Director, GAP &middot; Denton Bible Church</span></div></td>
 <td><div class="line"><b>Date</b></div></td>
</tr></table>
<div class="foot">GAP &middot; Denton Bible Church &middot; jpope@dentonbible.org &middot; 940.368.5286</div>
</body></html>
