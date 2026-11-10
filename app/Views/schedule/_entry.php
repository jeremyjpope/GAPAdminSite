<?php
/** One session card. Expects $e (entry), $courses (by id), $staff (entry_id => names), $slots (ordered list), optional $compact. */
$c = $courses[$e['course_id']] ?? ['name' => '?', 'pillar' => 'other', 'color' => null, 'audience' => 'coed'];
$compact ??= false;
$badge = ['confirmed' => 'success', 'done' => 'secondary', 'missed' => 'danger', 'cancelled' => 'dark'];
$slotAud = null;
foreach ($slots as $s) { if ($s['id'] == $e['slot_id']) { $slotAud = $s['audience']; } }
$aud = $e['audience'] ?: null;
?>
<a class="d-block text-decoration-none text-body small mb-1 ps-2 entry <?= $e['status'] === 'cancelled' ? 'text-decoration-line-through opacity-50' : '' ?>" style="border-left:4px solid <?= course_color($c) ?>;line-height:1.2" href="<?= site_url('schedule/entries/' . $e['id']) ?>" title="<?= esc($e['comment'] ?? '') ?>">
 <span class="fw-semibold"><?= esc($c['name']) ?></span><?php if ($e['slot_span'] > 1): ?><span class="text-secondary"> ⇥</span><?php endif ?>
 <?php if ($aud && $aud !== $slotAud && $aud !== 'coed'): ?><span class="badge rounded-pill text-bg-<?= $aud === 'guys' ? 'primary' : 'danger' ?> py-0"><?= $aud ?></span><?php endif ?>
 <?php if ($e['group_label']): ?><span class="badge rounded-pill text-bg-light py-0 border"><?= esc($e['group_label']) ?></span><?php endif ?>
 <?php if (isset($badge[$e['status']])): ?><span class="badge rounded-pill text-bg-<?= $badge[$e['status']] ?> py-0"><?= $e['status'] ?></span><?php endif ?>
 <?php if ($e['title']): ?><div><?= esc($compact ? mb_strimwidth($e['title'], 0, 60, '…') : $e['title']) ?></div><?php endif ?>
 <?php if (! $compact || ! empty($staff[$e['id']])): ?><div class="text-secondary"><?= esc(implode(', ', $staff[$e['id']] ?? [])) ?><?= $e['location'] ? ' <i class="bi bi-geo-alt"></i> ' . esc($e['location']) : '' ?><?= $e['lo_la'] && ! $compact ? ' <span class="font-monospace">' . esc($e['lo_la']) . '</span>' : '' ?></div><?php endif ?>
</a>
