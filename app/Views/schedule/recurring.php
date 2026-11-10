<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#rModal" onclick="editR(null)"><i class="bi bi-plus-lg"></i> New pattern</button><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= $this->include('schedule/_tabs') ?>
<p class="small text-secondary">The standing week: things that happen every Monday, every Tuesday… between two dates. Saving a pattern writes a real session onto each matching day (skipping days inside a break), so you can still change any single day on the week view. "Fill gaps" adds sessions for days that have none, e.g. after you extend the dates.</p>
<?php $byId = array_column($courseList, null, 'id'); $slotById = array_column($slots, null, 'id'); ?>
<div class="card"><table class="table table-sm table-hover mb-0 align-middle">
 <thead class="table-light"><tr><th>Day</th><th>Slot</th><th>Course</th><th>Detail</th><th>Dates</th><th class="text-end">Sessions</th><th></th></tr></thead><tbody>
 <?php foreach ($rows as $r): $c = $byId[$r['course_id']] ?? null; $s = $slotById[$r['slot_id']] ?? null; ?>
  <tr><td><?= $cfg->weekdays[$r['weekday']] ?? $r['weekday'] ?></td><td class="small"><?= $s ? esc($s['label']) : 'all day' ?><?= $r['slot_span'] > 1 ? ' ⇥' . $r['slot_span'] : '' ?></td>
   <td><span class="d-inline-block rounded-circle me-1" style="width:.6rem;height:.6rem;background:<?= $c ? course_color($c) : '#999' ?>"></span><?= esc($c['name'] ?? '?') ?> <?= $r['audience'] ? '<span class="badge text-bg-light">' . $r['audience'] . '</span>' : '' ?></td>
   <td class="small"><?= esc($r['title'] ?? '') ?> <span class="text-secondary"><?= esc($r['location'] ?? '') ?> <?= esc($r['lo_la'] ?? '') ?></span></td>
   <td class="small text-nowrap"><?= fmt_date($r['start_date'], 'M j') ?> – <?= fmt_date($r['end_date'], 'M j, Y') ?></td>
   <td class="text-end"><?= $counts[$r['id']] ?? 0 ?></td>
   <td class="text-end text-nowrap"><button class="btn btn-sm btn-link p-0 me-2" onclick='editR(<?= json_encode($r) ?>)'><i class="bi bi-pencil"></i></button>
    <form class="d-inline" method="post" action="<?= site_url('schedule/recurring/' . $r['id'] . '/generate') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link p-0 me-2" title="Fill gaps"><i class="bi bi-arrow-repeat"></i></button></form>
    <form class="d-inline" method="post" action="<?= site_url('schedule/recurring/' . $r['id'] . '/delete') ?>" onsubmit="return confirm('Delete this pattern AND every session it generated?')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form></td></tr>
 <?php endforeach ?>
 <?php if (! $rows): ?><tr><td colspan="7" class="text-secondary">No patterns yet.</td></tr><?php endif ?>
 </tbody></table></div>

<div class="modal fade" id="rModal"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="<?= site_url('schedule/recurring') ?>" id="rForm"><?= csrf_field() ?><input type="hidden" name="id">
 <div class="modal-header"><h5 class="modal-title">Standing-week pattern</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body row g-2">
  <div class="col-md-8"><label class="form-label">Course</label><select class="form-select" name="course_id" required><option value="">—</option><?php foreach ($courseList as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['name']) ?> (<?= esc($cfg->pillars[$c['pillar']][0] ?? '') ?>)</option><?php endforeach ?></select></div>
  <div class="col-md-4"><label class="form-label">Weekday</label><select class="form-select" name="weekday"><?php foreach ($cfg->weekdays as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach ?></select></div>
  <div class="col-md-6"><label class="form-label">Slot</label><select class="form-select" name="slot_id"><option value="">all day</option><?php foreach ($slots as $s): ?><option value="<?= $s['id'] ?>"><?= esc($s['label']) ?> (<?= esc(slot_time($s)) ?>)</option><?php endforeach ?></select></div>
  <div class="col-md-2"><label class="form-label">Spans</label><input class="form-control" type="number" min="1" max="8" name="slot_span" value="1"></div>
  <div class="col-md-4"><label class="form-label">Audience</label><select class="form-select" name="audience"><option value="">slot's</option><?php foreach ($cfg->audiences as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach ?></select></div>
  <div class="col-md-6"><label class="form-label">From</label><input class="form-control" type="date" name="start_date" value="<?= esc($year['start_date']) ?>"></div>
  <div class="col-md-6"><label class="form-label">To</label><input class="form-control" type="date" name="end_date" value="<?= esc($year['end_date']) ?>"></div>
  <div class="col-md-12"><label class="form-label">Detail</label><input class="form-control" name="title" placeholder="Run Training"></div>
  <div class="col-md-6"><label class="form-label">Group</label><select class="form-select" name="group_id"><option value="">—</option><?php foreach ($groups as $gid => $gl): ?><option value="<?= $gid ?>"><?= esc($gl) ?></option><?php endforeach ?></select></div>
  <div class="col-md-6"><label class="form-label">Location</label><input class="form-control" name="location"></div>
  <div class="col-md-6"><label class="form-label">LO / LA</label><input class="form-control font-monospace" name="lo_la"></div>
  <div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" name="notes"></div>
 </div><div class="modal-footer"><button class="btn btn-dark">Save and fill the calendar</button></div></form></div></div>
<script>function editR(r){const f=document.getElementById('rForm');for(const k of ['id','course_id','weekday','slot_id','slot_span','audience','start_date','end_date','title','group_id','location','lo_la','notes'])f.elements[k].value=r?(r[k]??''):(k==='slot_span'?1:k==='start_date'?<?= json_encode($year['start_date']) ?>:k==='end_date'?<?= json_encode($year['end_date']) ?>:'');if(r)new bootstrap.Modal(document.getElementById('rModal')).show();}</script>
<?= $this->endSection() ?>
