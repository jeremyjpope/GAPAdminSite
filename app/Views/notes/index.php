<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-outline-secondary" href="<?= $backUrl ?>">Back to <?= $type === 'student' ? 'student' : ($type === 'volunteer' ? 'volunteer' : 'host homes') ?></a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div style="max-width:860px"><?= view('notes/_panel', ['type' => $type, 'subjectId' => $subject['id'], 'notes' => $notes, 'returnUrl' => current_url()]) ?></div>
<?= $this->endSection() ?>
