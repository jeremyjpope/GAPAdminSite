<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('transcripts') ?>">Back</a> <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#cModal" onclick="editC(null)"><i class="bi bi-plus-lg"></i> Add course line</button><?= $this->endSection() ?>
<?= $this->section('content') ?>
<p class="text-secondary small">These are the lines printed on every <?= esc($year['label']) ?> transcript. Map a line to a Moodle course to pull its final grade automatically; unmapped lines are graded by hand.<?= $moodleError ? ' <span class="text-warning">Moodle course list unavailable: ' . esc($moodleError) . '</span>' : '' ?></p>
<div class="card"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>#</th><th>Pillar</th><th>Course</th><th>Description</th><th>Term</th><th class="text-end">Hours</th><th>Moodle course</th><th></th></tr></thead><tbody>
<?php $mc = array_column($moodleCourses, 'fullname', 'id'); foreach ($courses as $c): ?>
<tr><td class="text-secondary small"><?= $c['sort_order'] ?></td><td class="small"><?= esc($c['pillar']) ?></td><td class="fw-semibold"><?= esc($c['name']) ?></td><td class="small text-secondary"><?= esc($c['description']) ?></td><td class="small"><?= esc($c['term']) ?></td><td class="text-end"><?= $c['hours'] ?></td>
 <td class="small"><?= $c['moodle_course_id'] ? esc($mc[$c['moodle_course_id']] ?? ('course ' . $c['moodle_course_id'])) : '<span class="text-secondary">manual</span>' ?></td>
 <td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cModal" onclick='editC(<?= json_encode($c) ?>)'><i class="bi bi-pencil"></i></button>
  <form class="d-inline" method="post" action="<?= site_url('transcripts/catalog/' . $c['id'] . '/delete') ?>" onsubmit="return confirm('Remove this line and its grades?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
<?php endforeach ?>
</tbody></table></div>
<div class="modal fade" id="cModal"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="<?= site_url('transcripts/catalog') ?>" id="cForm"><?= csrf_field() ?><input type="hidden" name="id">
 <div class="modal-header"><h5 class="modal-title">Transcript course line</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body row g-2">
  <div class="col-md-4"><label class="form-label">Pillar / section</label><input class="form-control" name="pillar" list="pillars"><datalist id="pillars"><option>Pillar I: Bible</option><option>Pillar II: Worldview</option><option>Pillar III: Leadership</option><option>Pillar IV: Life Skills</option><option>Special Topics</option></datalist></div>
  <div class="col-md-5"><label class="form-label">Course</label><input class="form-control" name="name" required></div>
  <div class="col-md-3"><label class="form-label">Sort order</label><input class="form-control" name="sort_order" type="number"></div>
  <div class="col-md-12"><label class="form-label">Description</label><input class="form-control" name="description"></div>
  <div class="col-md-4"><label class="form-label">Term</label><input class="form-control" name="term" placeholder="Full Year"></div>
  <div class="col-md-2"><label class="form-label">Hours</label><input class="form-control" name="hours" type="number"></div>
  <div class="col-md-6"><label class="form-label">Moodle course (optional)</label><select class="form-select" name="moodle_course_id"><option value="">— graded by hand —</option><?php foreach ($moodleCourses as $m): ?><option value="<?= $m['id'] ?>"><?= esc($m['fullname']) ?></option><?php endforeach ?></select></div>
 </div><div class="modal-footer"><button class="btn btn-dark">Save</button></div></form></div></div>
<script>function editC(c){const f=document.getElementById('cForm');for(const k of ['id','pillar','name','sort_order','description','term','hours','moodle_course_id'])f.elements[k].value=c?(c[k]??''):'';}</script>
<?= $this->endSection() ?>
