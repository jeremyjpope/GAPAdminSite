<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><a class="btn btn-sm btn-outline-dark" href="<?= site_url('courses/' . $c['id'] . '/edit') ?>"><i class="bi bi-pencil"></i> Edit</a>
<a class="btn btn-sm btn-dark" href="<?= site_url('schedule/entries/new?course=' . $c['id']) ?>"><i class="bi bi-calendar-plus"></i> Schedule a session</a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="row g-3">
 <div class="col-lg-7">
  <div class="card mb-3"><div class="card-body">
   <div class="d-flex align-items-center gap-2 mb-2"><span class="d-inline-block rounded-circle" style="width:1rem;height:1rem;background:<?= course_color($c) ?>"></span>
    <span class="badge text-bg-light"><?= esc($cfg->pillars[$c['pillar']][0] ?? $c['pillar']) ?></span>
    <?php if ($c['category']): ?><span class="badge text-bg-light"><?= esc($c['category']) ?></span><?php endif ?>
    <span class="badge text-bg-light"><?= esc($cfg->audiences[$c['audience']] ?? '') ?></span>
    <?php if (! $c['active']): ?><span class="badge text-bg-secondary">inactive</span><?php endif ?>
    <?php if ($c['moodle_course_id']): ?><span class="badge text-bg-light">Moodle #<?= $c['moodle_course_id'] ?></span><?php endif ?></div>
   <?php if ($c['default_location']): ?><div class="small text-secondary mb-1"><i class="bi bi-geo-alt"></i> <?= esc($c['default_location']) ?></div><?php endif ?>
   <?php if ($c['description']): ?><div class="small" style="white-space:pre-wrap"><?= esc($c['description']) ?></div><?php endif ?>
  </div></div>

  <div class="card mb-3"><div class="card-header bg-white fw-semibold">Staff <span class="text-secondary fw-normal small">default for every session; add a sub or helper on the session itself</span></div>
   <table class="table table-sm mb-0 align-middle"><tbody>
   <?php foreach ($staff as $s): ?><tr><td><a class="text-decoration-none" href="<?= site_url('volunteers/' . $s['volunteer_id']) ?>"><?= esc($s['first_name'] . ' ' . $s['last_name']) ?></a> <span class="badge text-bg-light"><?= esc($cfg->staffRoles[$s['role']] ?? $s['role']) ?></span> <span class="small text-secondary"><?= esc($s['notes'] ?? '') ?></span></td>
    <td class="small text-secondary"><?= esc($s['email'] ?? '') ?> <?= esc($s['phone'] ?? '') ?></td>
    <td class="text-end"><form method="post" action="<?= site_url('courses/' . $c['id'] . '/staff/' . $s['id'] . '/remove') ?>" onsubmit="return confirm('Remove?')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form></td></tr><?php endforeach ?>
   <?php if (! $staff): ?><tr><td class="text-secondary small">No one assigned yet.</td></tr><?php endif ?>
   </tbody></table>
   <form class="card-footer bg-white row g-2 align-items-end" method="post" action="<?= site_url('courses/' . $c['id'] . '/staff') ?>"><?= csrf_field() ?>
    <div class="col-md-5"><select class="form-select form-select-sm" name="volunteer_id" required><option value="">Add a person…</option><?php foreach ($volunteers as $v): ?><option value="<?= $v['id'] ?>"><?= esc($v['last_name'] . ', ' . $v['first_name']) ?></option><?php endforeach ?></select></div>
    <div class="col-md-3"><select class="form-select form-select-sm" name="role"><?php foreach ($cfg->staffRoles as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach ?></select></div>
    <div class="col-md-3"><input class="form-control form-control-sm" name="notes" placeholder="note"></div>
    <div class="col-md-1"><button class="btn btn-sm btn-dark w-100">Add</button></div>
    <div class="col-12 form-text">Anyone on the volunteer list (leadership team included) can be assigned. Missing someone? <a href="<?= site_url('volunteers/new') ?>">Add them to Volunteers</a> first.</div>
   </form></div>

  <div class="card mb-3"><div class="card-header bg-white fw-semibold">Assets <span class="text-secondary fw-normal small">curriculum docs, slides, links</span></div>
   <table class="table table-sm mb-0 align-middle"><tbody>
   <?php foreach ($assets as $a): ?><tr><td><?php if ($a['file_path']): ?><a href="<?= site_url('courses/' . $c['id'] . '/assets/' . $a['id']) ?>"><i class="bi bi-file-earmark-arrow-down"></i> <?= esc($a['title']) ?></a><?php else: ?><a href="<?= esc($a['url']) ?>" target="_blank" rel="noopener"><i class="bi bi-link-45deg"></i> <?= esc($a['title']) ?></a><?php endif ?> <span class="small text-secondary"><?= esc($a['notes'] ?? '') ?></span></td>
    <td class="text-end"><form method="post" action="<?= site_url('courses/' . $c['id'] . '/assets/' . $a['id'] . '/remove') ?>" onsubmit="return confirm('Remove?')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form></td></tr><?php endforeach ?>
   <?php if (! $assets): ?><tr><td class="text-secondary small">Nothing attached yet.</td></tr><?php endif ?>
   </tbody></table>
   <form class="card-footer bg-white row g-2 align-items-end" method="post" enctype="multipart/form-data" action="<?= site_url('courses/' . $c['id'] . '/assets') ?>"><?= csrf_field() ?>
    <div class="col-md-4"><input class="form-control form-control-sm" name="title" placeholder="Title"></div>
    <div class="col-md-4"><input class="form-control form-control-sm" name="url" placeholder="https://… (link)"></div>
    <div class="col-md-3"><input class="form-control form-control-sm" type="file" name="file"></div>
    <div class="col-md-1"><button class="btn btn-sm btn-dark w-100">Add</button></div>
   </form></div>
 </div>
 <div class="col-lg-5">
  <div class="card mb-3"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Sessions in <?= esc($year['label'] ?? '') ?></span><span class="small text-secondary"><?= count($entries) ?> · <?= rtrim(rtrim(number_format($hours, 1), '0'), '.') ?> h</span></div>
   <div class="list-group list-group-flush" style="max-height:28rem;overflow:auto">
   <?php foreach ($entries as $e): $s = $slots[$e['slot_id']] ?? null; ?>
    <a class="list-group-item list-group-item-action small py-1" href="<?= site_url('schedule/entries/' . $e['id']) ?>">
     <span class="text-secondary"><?= fmt_date($e['start_date'], 'D M j') ?><?= $e['end_date'] !== $e['start_date'] ? ' – ' . fmt_date($e['end_date'], 'M j') : '' ?></span>
     <?= $s ? '<span class="text-secondary">· ' . esc(slot_time($s)) . '</span>' : '<span class="text-secondary">· all day</span>' ?>
     <?= $e['title'] ? ' ' . esc($e['title']) : '' ?> <?= $e['group_label'] ? '<span class="badge text-bg-light">' . esc($e['group_label']) . '</span>' : '' ?>
     <?php if (! empty($entryStaff[$e['id']])): ?><span class="text-secondary">· <?= esc(implode(', ', $entryStaff[$e['id']])) ?></span><?php endif ?>
     <?php if ($e['status'] !== 'planned'): ?><span class="badge text-bg-<?= ['confirmed' => 'success', 'done' => 'secondary', 'missed' => 'danger', 'cancelled' => 'dark'][$e['status']] ?? 'light' ?>"><?= $e['status'] ?></span><?php endif ?>
    </a>
   <?php endforeach ?>
   <?php if (! $entries): ?><div class="list-group-item text-secondary small">Not on this year's schedule.</div><?php endif ?>
   </div></div>
  <?= view('notes/_panel', ['type' => 'course', 'subjectId' => $c['id'], 'notes' => $notes, 'returnUrl' => site_url('courses/' . $c['id']) . '#notes']) ?>
  <form class="mt-3 row g-2 align-items-center" method="post" action="<?= site_url('courses/' . $c['id'] . '/merge') ?>" onsubmit="return confirm('Move everything on this course to the selected one and delete this course?')"><?= csrf_field() ?>
   <div class="col-auto small text-secondary">Duplicate?</div>
   <div class="col"><select class="form-select form-select-sm" name="into" required><option value="">Merge into…</option><?php foreach ($allCourses as $o): if ($o['id'] == $c['id']) continue; ?><option value="<?= $o['id'] ?>"><?= esc($o['name']) ?> (<?= esc($cfg->pillars[$o['pillar']][0] ?? '') ?>)</option><?php endforeach ?></select></div>
   <div class="col-auto"><button class="btn btn-sm btn-outline-dark">Merge</button></div>
  </form>
  <form class="mt-3 text-end" method="post" action="<?= site_url('courses/' . $c['id'] . '/delete') ?>" onsubmit="return confirm('Delete this course?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete course</button></form>
 </div>
</div>
<?= $this->endSection() ?>
