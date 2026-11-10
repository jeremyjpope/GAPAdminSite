<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $x = $e; $isNew = empty($x['id']); $val = fn ($k) => esc(old($k, $x[$k] ?? '')); $ret = service('request')->getGet('return'); ?>
<div class="row g-3">
 <div class="col-lg-7">
 <form method="post" action="<?= $isNew ? site_url('schedule/entries') : site_url('schedule/entries/' . $x['id']) ?>"><?= csrf_field() ?>
  <?php if ($ret): ?><input type="hidden" name="return" value="<?= esc($ret) ?>"><?php endif ?>
  <div class="card mb-3"><div class="card-body row g-3">
   <div class="col-12"><label class="form-label">Course</label><select class="form-select" name="course_id" required>
    <option value="">— pick a course —</option>
    <?php $lp = null; foreach ($courseList as $c): if ($c['pillar'] !== $lp): if ($lp !== null) echo '</optgroup>'; $lp = $c['pillar']; echo '<optgroup label="' . esc($cfg->pillars[$lp][0] ?? $lp) . '">'; endif ?>
     <option value="<?= $c['id'] ?>" <?= (int) old('course_id', $x['course_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?><?= $c['category'] ? ' · ' . esc($c['category']) : '' ?></option>
    <?php endforeach; if ($lp !== null) echo '</optgroup>'; ?></select>
    <div class="form-text">Not in the list? <a href="<?= site_url('courses/new') ?>">Add a course</a>.</div></div>
   <div class="col-md-4"><label class="form-label">Date</label><input class="form-control" type="date" name="start_date" value="<?= $val('start_date') ?>" required></div>
   <div class="col-md-4"><label class="form-label">Through <span class="text-secondary">(multi-day)</span></label><input class="form-control" type="date" name="end_date" value="<?= $val('end_date') ?>"></div>
   <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach ($cfg->statuses as $k => $l): ?><option value="<?= $k ?>" <?= ($x['status'] ?? 'planned') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
   <div class="col-md-6"><label class="form-label">Slot</label><select class="form-select" name="slot_id" id="slot_id">
    <?php foreach ($slots as $s): ?><option value="<?= $s['id'] ?>" <?= (int) ($x['slot_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['label']) ?> (<?= esc(slot_time($s)) ?>)</option><?php endforeach ?></select>
    <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="all_day" value="1" id="all_day" <?= ! $isNew && empty($x['slot_id']) ? 'checked' : '' ?> onchange="document.getElementById('slot_id').disabled=this.checked"><label class="form-check-label" for="all_day">All day / no slot (break, trip, event)</label></div></div>
   <div class="col-md-2"><label class="form-label">Spans slots</label><input class="form-control" type="number" min="1" max="8" name="slot_span" value="<?= $val('slot_span') ?: 1 ?>"></div>
   <div class="col-md-4"><label class="form-label">Audience <span class="text-secondary">(blank = slot's)</span></label><select class="form-select" name="audience"><option value="">—</option><?php foreach ($cfg->audiences as $k => $l): ?><option value="<?= $k ?>" <?= ($x['audience'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
   <div class="col-12"><label class="form-label">Session detail <span class="text-secondary">(what this particular session is)</span></label><input class="form-control" name="title" value="<?= $val('title') ?>" placeholder="Woodworking · Career Moment: Clint Webb · Welding"></div>
   <div class="col-md-6"><label class="form-label">Group</label><select class="form-select" name="group_id"><option value="">—</option><?php foreach ($groups as $gid => $gl): ?><option value="<?= $gid ?>" <?= (int) ($x['group_id'] ?? 0) === (int) $gid ? 'selected' : '' ?>><?= esc($gl) ?></option><?php endforeach ?></select></div>
   <div class="col-md-6"><label class="form-label">Group label <span class="text-secondary">(free text, e.g. "A & B")</span></label><input class="form-control" name="group_label" value="<?= $val('group_label') ?>"></div>
   <div class="col-md-7"><label class="form-label">Location</label><input class="form-control" name="location" value="<?= $val('location') ?>"></div>
   <div class="col-md-5"><label class="form-label">LO / LA codes</label><input class="form-control font-monospace" name="lo_la" value="<?= $val('lo_la') ?>" placeholder="LO2/LA1,2"></div>
   <div class="col-12"><label class="form-label">Comment <span class="text-secondary">(reminders, confirmations, contacts)</span></label><textarea class="form-control" name="comment" rows="3"><?= $val('comment') ?></textarea></div>
  </div></div>
  <button class="btn btn-dark"><?= $isNew ? 'Add session' : 'Save' ?></button>
  <a class="btn btn-link" href="<?= $ret ?: site_url('schedule?date=' . ($x['start_date'] ?? date('Y-m-d'))) ?>">Back to week</a>
  <?php if (! empty($x['recurrence_id'])): ?><span class="ms-2 small text-secondary"><i class="bi bi-arrow-repeat"></i> generated from a <a href="<?= site_url('schedule/recurring') ?>">standing-week pattern</a>; edits here apply to this day only.</span><?php endif ?>
 </form>
 <?php if (! $isNew): ?><form class="mt-3" method="post" action="<?= site_url('schedule/entries/' . $x['id'] . '/delete') ?>" onsubmit="return confirm('Delete this session?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete session</button></form><?php endif ?>
 </div>
 <div class="col-lg-5">
 <?php if (! $isNew): ?>
  <div class="card mb-3"><div class="card-header bg-white fw-semibold">People for this session</div>
   <table class="table table-sm mb-0 align-middle"><tbody>
   <?php foreach ($entryStaff ?? [] as $s): ?><tr><td><a class="text-decoration-none" href="<?= site_url('volunteers/' . $s['volunteer_id']) ?>"><?= esc($s['first_name'] . ' ' . $s['last_name']) ?></a> <span class="badge text-bg-light"><?= esc($cfg->staffRoles[$s['role']] ?? $s['role']) ?></span><div class="small text-secondary"><?= esc($s['email'] ?? '') ?> <?= esc($s['phone'] ?? '') ?></div></td>
    <td class="text-end"><form method="post" action="<?= site_url('schedule/entries/' . $x['id'] . '/staff/' . $s['id'] . '/remove') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form></td></tr><?php endforeach ?>
   <?php if (empty($entryStaff)): ?><tr><td class="small text-secondary">Nobody yet. The course's default staff are copied when a session is created; add a sub, helper or guest here.</td></tr><?php endif ?>
   </tbody></table>
   <form class="card-footer bg-white row g-2" method="post" action="<?= site_url('schedule/entries/' . $x['id'] . '/staff') ?>"><?= csrf_field() ?>
    <div class="col-7"><select class="form-select form-select-sm" name="volunteer_id" required><option value="">Add a person…</option><?php foreach ($volunteers as $v): ?><option value="<?= $v['id'] ?>"><?= esc($v['last_name'] . ', ' . $v['first_name']) ?></option><?php endforeach ?></select></div>
    <div class="col-3"><select class="form-select form-select-sm" name="role"><?php foreach ($cfg->staffRoles as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'sub' ? '' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
    <div class="col-2"><button class="btn btn-sm btn-dark w-100">Add</button></div>
   </form></div>
 <?php else: ?>
  <div class="card"><div class="card-body small text-secondary">The course's default instructors are attached automatically once the session is saved; you can then add a substitute or helper for this day.</div></div>
 <?php endif ?>
 </div>
</div>
<?= $this->endSection() ?>
