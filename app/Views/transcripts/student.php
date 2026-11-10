<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?>
<div class="d-flex gap-2"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('transcripts') ?>">All students</a>
<form method="post" action="<?= site_url('transcripts/' . $s['id'] . '/pull') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-cloud-download"></i> Pull grades from Moodle</button></form>
<a class="btn btn-sm btn-dark" target="_blank" href="<?= site_url('transcripts/' . $s['id'] . '/pdf') ?>"><i class="bi bi-file-earmark-pdf"></i> View PDF</a></div>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('transcripts/' . $s['id'] . '/grades') ?>"><?= csrf_field() ?>
<div class="row g-3">
 <div class="col-lg-8"><div class="card"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Course</th><th>Term</th><th class="text-end">Hours</th><th style="width:110px">Grade</th><th class="small">Source</th></tr></thead><tbody>
 <?php $pillar = null; foreach ($courses as $c): $g = $grades[$c['id']] ?? null; if ($c['pillar'] !== $pillar): $pillar = $c['pillar']; ?><tr class="table-light"><td colspan="5" class="small fw-semibold text-uppercase"><?= esc($pillar) ?></td></tr><?php endif ?>
 <tr><td><?= esc($c['name']) ?><div class="small text-secondary"><?= esc($c['description']) ?></div></td><td class="small"><?= esc($c['term']) ?></td><td class="text-end"><?= $c['hours'] ?></td>
  <td><input class="form-control form-control-sm text-center" name="letter[<?= $c['id'] ?>]" value="<?= esc($g['letter'] ?? '') ?>" maxlength="3" placeholder="—"></td>
  <td class="small text-secondary"><?= $g ? esc($g['source']) . ($g['percent'] !== null ? ' ' . round($g['percent'], 1) . '%' : '') : '' ?></td></tr>
 <?php endforeach ?>
 </tbody></table></div></div>
 <div class="col-lg-4">
  <div class="card mb-3"><div class="card-body">
   <div class="mb-2"><label class="form-label">Program status</label><select class="form-select" name="status"><?php foreach (['enrolled' => 'Enrolled', 'graduated' => 'Graduated', 'withdrawn' => 'Withdrawn'] as $k => $l): ?><option value="<?= $k ?>" <?= $s['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
   <div class="mb-2"><label class="form-label">Attended from</label><input class="form-control" name="attended_from" value="<?= esc($s['attended_from'] ?? '') ?>" placeholder="August <?= substr($year['label'], 0, 4) ?>"></div>
   <div class="mb-2"><label class="form-label">Attended to</label><input class="form-control" name="attended_to" value="<?= esc($s['attended_to'] ?? '') ?>" placeholder="May 20<?= substr($year['label'], -2) ?>"></div>
   <button class="btn btn-dark w-100">Save grades</button>
  </div></div>
  <div class="card"><div class="card-body small">
   <div class="d-flex justify-content-between"><span>Courses completed</span><strong><?= $summary['courses'] ?></strong></div>
   <div class="d-flex justify-content-between"><span>Instructional hours</span><strong><?= $summary['hours'] ?></strong></div>
   <div class="d-flex justify-content-between"><span>Cumulative GPA</span><strong><?= $summary['gpa'] !== null ? number_format($summary['gpa'], 2) : '—' ?></strong></div>
   <hr><div class="text-secondary">Letters come from the year's scale (<?= esc(implode(' · ', array_map(fn ($r) => $r['letter'] . ' ≥ ' . $r['min'], json_decode($year['grade_scale'] ?? '[]', true) ?: []))) ?>). Pulling from Moodle converts the course total with that scale; you can overwrite any letter by hand.</div>
  </div></div>
 </div>
</div>
</form>
<?= $this->endSection() ?>
