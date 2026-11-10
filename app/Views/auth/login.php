<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in · GAP Admin</title>
<link rel="icon" type="image/png" href="<?= base_url('favicon.png') ?>">
<link rel="apple-touch-icon" href="<?= base_url('apple-touch-icon.png') ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#2f2f2f;min-height:100vh;display:flex;align-items:center}.card{border:0}.brand{letter-spacing:.25em;font-weight:700}</style>
</head><body>
<div class="container"><div class="row justify-content-center"><div class="col-md-5 col-lg-4">
  <div class="text-center text-white mb-4"><img src="<?= base_url('img/gap-logo-light.png') ?>" alt="GAP" style="height:84px" class="mb-2"><div class="small" style="letter-spacing:.2em">GOD'S ALTERNATIVE PROGRAM</div></div>
  <div class="card shadow"><div class="card-body p-4">
    <h1 class="h5 mb-3">Admin log in</h1>
    <?= flash_alerts() ?>
    <form method="post" action="<?= site_url('login') ?>"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Moodle username or email</label><input class="form-control" name="username" value="<?= esc(old('username')) ?>" autofocus required></div>
      <div class="mb-3"><label class="form-label">Moodle password</label><input class="form-control" type="password" name="password" required></div>
      <button class="btn btn-dark w-100">Log in</button>
    </form>
    <?php if (ENVIRONMENT === 'development'): ?><a class="btn btn-outline-secondary btn-sm w-100 mt-2" href="<?= site_url('dev-login') ?>">Dev shortcut: log in as super admin</a><?php endif ?>
    <p class="text-secondary small mt-3 mb-0">Use the same login as <a href="<?= esc(config('Moodle')->url) ?>">moodle.gapdbc.org</a>. Only staff who have been granted access can enter.</p>
  </div></div>
</div></div></div>
</body></html>
