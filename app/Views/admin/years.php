<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#yModal" onclick="editY(null)"><i class="bi bi-plus-lg"></i> Add program year</button><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Year</th><th>Dates</th><th>Moodle overall course</th><th>Tuition (early / late)</th><th></th><th></th></tr></thead><tbody>
<?php foreach ($years as $y): ?>
<tr><td class="fw-semibold"><?= esc($y['label']) ?> <span class="text-secondary">· Year <?= $y['year_number'] ?></span> <?= $y['is_current'] ? '<span class="badge bg-success">current</span>' : '' ?></td><td class="small"><?= fmt_date($y['start_date']) ?> – <?= fmt_date($y['end_date']) ?></td><td class="small"><?= $y['moodle_overall_course_id'] ?: '—' ?></td><td class="small"><?= money($y['tuition_year_early']) ?> / <?= money($y['tuition_year_late']) ?> (cutoff <?= fmt_date($y['tuition_cutoff_date']) ?>)</td><td></td>
 <td class="text-end"><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#yModal" onclick='editY(<?= json_encode($y) ?>)'><i class="bi bi-pencil"></i></button></td></tr>
<?php endforeach ?>
</tbody></table></div>
<div class="modal fade" id="yModal"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="<?= site_url('admin/years') ?>" id="yForm"><?= csrf_field() ?><input type="hidden" name="id">
 <div class="modal-header"><h5 class="modal-title">Program year</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body row g-2">
  <div class="col-md-3"><label class="form-label">Label</label><input class="form-control" name="label" placeholder="2027-28" required></div>
  <div class="col-md-2"><label class="form-label">Year #</label><input class="form-control" name="year_number" type="number"></div>
  <div class="col-md-3"><label class="form-label">Start</label><input class="form-control" name="start_date" type="date"></div>
  <div class="col-md-3"><label class="form-label">End</label><input class="form-control" name="end_date" type="date"></div>
  <div class="col-md-4"><label class="form-label">Moodle overall-standing course id</label><input class="form-control" name="moodle_overall_course_id" type="number"></div>
  <div class="col-md-8"><label class="form-label">Program description (transcript)</label><input class="form-control" name="program_description"></div>
  <div class="col-md-3"><label class="form-label">Tuition early</label><input class="form-control" name="tuition_year_early"></div>
  <div class="col-md-3"><label class="form-label">Tuition late</label><input class="form-control" name="tuition_year_late"></div>
  <div class="col-md-3"><label class="form-label">Late applies on/after</label><input class="form-control" name="tuition_cutoff_date" type="date"></div>
  <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_current" value="1" id="is_current"><label class="form-check-label" for="is_current">Current year</label></div></div>
  <div class="col-12"><label class="form-label">Transcript note ("Also completed…")</label><textarea class="form-control" name="transcript_notes" rows="2"></textarea></div>
  <div class="col-12"><label class="form-label">Grade scale (JSON)</label><textarea class="form-control font-monospace small" name="grade_scale" rows="2"></textarea></div>
 </div><div class="modal-footer"><button class="btn btn-dark">Save</button></div></form></div></div>
<script>function editY(y){const f=document.getElementById('yForm');for(const k of ['id','label','year_number','start_date','end_date','moodle_overall_course_id','program_description','tuition_year_early','tuition_year_late','tuition_cutoff_date','transcript_notes','grade_scale'])f.elements[k].value=y?(y[k]??''):'';f.elements['is_current'].checked=!!(y&&+y.is_current);}</script>
<?= $this->endSection() ?>
