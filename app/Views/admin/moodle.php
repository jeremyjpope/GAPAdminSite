<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card" style="max-width:720px"><div class="card-body">
 <dl class="row mb-0">
  <dt class="col-sm-4">Moodle URL</dt><dd class="col-sm-8"><a href="<?= esc($cfg->url) ?>" target="_blank"><?= esc($cfg->url) ?></a></dd>
  <dt class="col-sm-4">Login service</dt><dd class="col-sm-8"><code><?= esc($cfg->loginService) ?></code> <span class="text-secondary small">— used to verify a user's password at login; nothing is stored.</span></dd>
  <dt class="col-sm-4">Service token</dt><dd class="col-sm-8">
   <?php if (! $cfg->wsToken): ?><span class="text-warning">Not set.</span> Add <code>moodle.wsToken</code> to <code>.env</code>. In Moodle: Site administration → Server → Web services → Manage tokens → Create token (user: an admin, service: Moodle mobile web service).
   <?php elseif ($error): ?><span class="text-danger">Configured but failing:</span> <?= esc($error) ?>
   <?php else: ?><span class="text-success">Working.</span> Connected as <b><?= esc($info['fullname'] ?? '') ?></b> (<?= esc($info['username'] ?? '') ?>) to <?= esc($info['sitename'] ?? '') ?>, Moodle <?= esc($info['release'] ?? '') ?>.<?php endif ?>
  </dd>
 </dl>
</div></div>
<?= $this->endSection() ?>
