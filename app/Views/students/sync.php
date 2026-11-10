<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card" style="max-width:640px"><div class="card-body">
<p>This reads everyone enrolled as a <em>student</em> in the year's Moodle overall-standing course (course id <?= (int) ($year['moodle_overall_course_id'] ?? 0) ?>) and creates a student record for <?= esc($year['label']) ?> for each one that is not already here. Existing students are matched by Moodle user id, then email, then name, and are linked rather than duplicated.</p>
<p class="text-secondary small">Requires a Moodle web service token in <code>.env</code> (Site admin → Moodle shows the connection status).</p>
<form method="post"><?= csrf_field() ?><button class="btn btn-dark"><i class="bi bi-arrow-repeat"></i> Run sync</button> <a class="btn btn-link" href="<?= site_url('students') ?>">Cancel</a></form>
</div></div>
<?= $this->endSection() ?>
