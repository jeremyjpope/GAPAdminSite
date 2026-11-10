<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $x = $c ?? []; $val = fn ($k) => esc(old($k, $x[$k] ?? '')); ?>
<form method="post" action="<?= $x ? site_url('courses/' . $x['id']) : site_url('courses') ?>" style="max-width:800px"><?= csrf_field() ?>
 <div class="card mb-3"><div class="card-body row g-3">
  <div class="col-md-8"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= $val('name') ?>" required></div>
  <div class="col-md-4"><label class="form-label">Pillar / aspect</label><select class="form-select" name="pillar"><?php foreach ($cfg->pillars as $k => [$l]): ?><option value="<?= $k ?>" <?= ($x['pillar'] ?? 'other') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
  <div class="col-md-4"><label class="form-label">Life-skills category</label><select class="form-select" name="category"><option value="">—</option><?php foreach ($cfg->categories as $cat): ?><option <?= ($x['category'] ?? '') === $cat ? 'selected' : '' ?>><?= esc($cat) ?></option><?php endforeach ?></select></div>
  <div class="col-md-3"><label class="form-label">Audience</label><select class="form-select" name="audience"><?php foreach ($cfg->audiences as $k => $l): ?><option value="<?= $k ?>" <?= ($x['audience'] ?? 'coed') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
  <div class="col-md-2"><label class="form-label">Colour</label><input class="form-control form-control-color w-100" type="color" name="color" value="<?= $val('color') ?: '#6c757d' ?>"></div>
  <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" id="active" <?= ($x['active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="active">Active</label></div></div>
  <div class="col-md-8"><label class="form-label">Default location</label><input class="form-control" name="default_location" value="<?= $val('default_location') ?>" placeholder="Hangar Classroom"></div>
  <div class="col-md-4"><label class="form-label">Moodle course id <span class="text-secondary">(for later sync)</span></label><input class="form-control" type="number" name="moodle_course_id" value="<?= $val('moodle_course_id') ?>"></div>
  <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= $val('description') ?></textarea></div>
 </div></div>
 <button class="btn btn-dark">Save</button> <a class="btn btn-link" href="<?= $x ? site_url('courses/' . $x['id']) : site_url('courses') ?>">Cancel</a>
 <p class="form-text mt-2">Leave the colour at grey to use the pillar's colour on the schedule.</p>
</form>
<?= $this->endSection() ?>
