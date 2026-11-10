<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('transcripts/catalog') ?>"><i class="bi bi-list-ul"></i> Course catalog (<?= $courseCount ?>)</a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Student</th><th>Status</th><th>Attended</th><th>Grades entered</th><th></th></tr></thead><tbody>
<?php foreach ($students as $s): ?>
<tr><td><a class="fw-semibold text-decoration-none" href="<?= site_url('transcripts/' . $s['id']) ?>"><?= esc($s['last_name']) ?>, <?= esc($s['first_name']) ?></a></td>
 <td><span class="badge text-bg-light"><?= esc($s['status']) ?></span></td><td class="small"><?= esc($s['attended_from']) ?><?= $s['attended_to'] ? ' – ' . esc($s['attended_to']) : '' ?></td>
 <td><?= $counts[$s['id']] ?? 0 ?> / <?= $courseCount ?></td>
 <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('transcripts/' . $s['id'] . '/pdf') ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?= $this->endSection() ?>
