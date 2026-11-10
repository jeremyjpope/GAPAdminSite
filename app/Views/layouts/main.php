<?php use App\Libraries\Auth; $u = Auth::user(); $yr = current_year(); $areas = config('Areas')->list; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title ?? 'GAP Admin') ?> · GAP Admin</title>
<link rel="icon" type="image/png" href="<?= base_url('favicon.png') ?>">
<link rel="apple-touch-icon" href="<?= base_url('apple-touch-icon.png') ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  :root { --gap-green: #5b6b3a; --gap-dark: #2f2f2f; }
  body { background: #f6f6f3; }
  .sidebar { background: var(--gap-dark); min-height: 100vh; }
  .sidebar .nav-link { color: #cfcfc8; border-radius: .4rem; }
  .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,.08); color: #fff; }
  .sidebar .brand { color:#fff; letter-spacing:.2em; font-weight:700; }
  .sidebar .brand small { color: var(--gap-green); }
  .card { border: 0; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
  .stat { font-size: 1.6rem; font-weight: 600; }
  .avatar { width: 48px; height: 60px; object-fit: cover; border-radius: .3rem; background:#ddd; }
  .avatar-lg { width: 160px; height: 200px; object-fit: cover; border-radius: .5rem; background:#ddd; }
  table.table-sm td, table.table-sm th { vertical-align: middle; }
  .form-label { font-size:.8rem; text-transform: uppercase; letter-spacing:.05em; color:#666; margin-bottom:.15rem; }
</style>
</head>
<body>
<div class="container-fluid">
<div class="row">
  <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3">
    <a href="<?= site_url('/') ?>" class="d-block brand text-decoration-none mb-4"><img src="<?= base_url('img/gap-logo-light.png') ?>" alt="GAP" style="height:56px"><span class="d-block small fw-normal mt-1" style="letter-spacing:.15em">GAP ADMIN</span></a>
    <?php if (count(all_years()) > 1): ?>
    <form method="post" action="<?= site_url('year/switch') ?>" class="mb-3"><?= csrf_field() ?>
      <select name="year_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php foreach (all_years() as $y): ?><option value="<?= $y['id'] ?>" <?= $y['id'] == ($yr['id'] ?? 0) ? 'selected' : '' ?>><?= esc($y['label']) ?> (Year <?= $y['year_number'] ?>)</option><?php endforeach ?>
      </select>
    </form>
    <?php else: ?><div class="text-secondary small mb-3"><?= esc($yr['label'] ?? '') ?> · Year <?= esc($yr['year_number'] ?? '') ?></div><?php endif ?>
    <?php
      $nav = [
        ['/', 'Dashboard', 'speedometer2', null],
        ['students', 'Students', 'people', 'students'],
        ['tuition', 'Tuition', 'cash-coin', 'tuition'],
        ['housing', 'Host Homes', 'house-heart', 'housing'],
        ['mentors', 'Mentors', 'person-check', 'mentors'],
        ['groups', 'Groups', 'diagram-3', 'groups'],
        ['volunteers', 'Volunteers', 'people-fill', 'volunteers'],
        ['transcripts', 'Transcripts', 'file-earmark-text', 'transcripts'],
      ];
      $path = trim(uri_string(), '/');
    ?>
    <ul class="nav flex-column gap-1">
      <?php foreach ($nav as [$href, $label, $icon, $area]): if ($area && ! Auth::can($area)) continue;
        $active = $href === '/' ? $path === '' : str_starts_with($path, $href); ?>
      <li class="nav-item"><a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= site_url($href) ?>"><i class="bi bi-<?= $icon ?> me-2"></i><?= $label ?></a></li>
      <?php endforeach ?>
      <?php if (Auth::can('admin')): ?>
      <li class="mt-3 small text-secondary text-uppercase px-2">Site admin</li>
      <li class="nav-item"><a class="nav-link <?= str_starts_with($path, 'admin/users') ? 'active' : '' ?>" href="<?= site_url('admin/users') ?>"><i class="bi bi-shield-lock me-2"></i>Access</a></li>
      <li class="nav-item"><a class="nav-link <?= str_starts_with($path, 'admin/years') ? 'active' : '' ?>" href="<?= site_url('admin/years') ?>"><i class="bi bi-calendar3 me-2"></i>Program years</a></li>
      <li class="nav-item"><a class="nav-link <?= str_starts_with($path, 'admin/moodle') ? 'active' : '' ?>" href="<?= site_url('admin/moodle') ?>"><i class="bi bi-plug me-2"></i>Moodle</a></li>
      <?php endif ?>
    </ul>
    <hr class="border-secondary">
    <div class="small text-secondary px-2"><?= esc($u['fullname'] ?? '') ?><?= Auth::isSuper() ? ' <span class="badge bg-success">super</span>' : '' ?><br><a class="link-light" href="<?= site_url('logout') ?>">Log out</a></div>
  </nav>
  <main class="col-md-9 col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0"><?= esc($title ?? '') ?></h1>
      <?= $this->renderSection('actions') ?>
    </div>
    <?= flash_alerts() ?>
    <?= $this->renderSection('content') ?>
  </main>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
