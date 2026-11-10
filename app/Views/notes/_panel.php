<?php
/**
 * Notes panel. Expects: $type ('student'|'host_home'), $subjectId, $notes (array), optional $returnUrl, optional $compact (bool).
 */
use App\Libraries\Auth;
use App\Models\NoteModel;

$returnUrl ??= current_url();
$compact   ??= false;
$base = site_url('notes/' . $type . '/' . $subjectId);
$badge = ['strike' => 'danger', 'discipline' => 'warning', 'contact' => 'info', 'housing' => 'secondary', 'medical' => 'primary', 'praise' => 'success', 'general' => 'light'];
?>
<div class="card" id="notes">
 <div class="card-header bg-white d-flex justify-content-between align-items-center">
  <span class="fw-semibold">Notes <span class="badge text-bg-light"><?= count($notes) ?></span></span>
  <?php if ($compact): ?><a class="small" href="<?= $base ?>">Full log</a><?php endif ?>
 </div>
 <form class="card-body border-bottom row g-2" method="post" action="<?= $base ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= esc($returnUrl) ?>">
  <div class="col-sm-3"><label class="form-label">Date</label><input class="form-control form-control-sm" type="date" name="noted_on" value="<?= date('Y-m-d') ?>" required></div>
  <div class="col-sm-3"><label class="form-label">Time <span class="text-secondary">(optional)</span></label><input class="form-control form-control-sm" type="time" name="noted_time"></div>
  <div class="col-sm-6"><label class="form-label">Category</label><select class="form-select form-select-sm" name="category"><?php foreach (NoteModel::CATEGORIES as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach ?></select></div>
  <div class="col-12"><textarea class="form-control form-control-sm" name="body" rows="2" placeholder="What happened, who was involved, what was decided…" required></textarea></div>
  <div class="col-12 d-flex justify-content-between align-items-center"><span class="small text-secondary">Will be signed by <?= esc(Auth::user()['fullname'] ?? '') ?></span><button class="btn btn-sm btn-dark">Add note</button></div>
 </form>
 <?php if (! $notes): ?><div class="card-body text-secondary small">No notes yet.</div><?php endif ?>
 <ul class="list-group list-group-flush">
 <?php foreach ($notes as $n): $mine = (int) $n['user_id'] === Auth::id() || Auth::isSuper(); ?>
  <li class="list-group-item">
   <div class="d-flex justify-content-between align-items-start">
    <div>
     <span class="fw-semibold"><?= fmt_date($n['noted_on'], 'D M j, Y') ?></span><?= $n['noted_time'] ? ' <span class="text-secondary">' . esc(date('g:i a', strtotime($n['noted_time']))) . '</span>' : '' ?>
     <?php if ($n['category']): ?> <span class="badge text-bg-<?= $badge[$n['category']] ?? 'light' ?>"><?= esc(NoteModel::CATEGORIES[$n['category']] ?? $n['category']) ?></span><?php endif ?>
    </div>
    <?php if ($mine): ?><span class="text-nowrap">
     <button class="btn btn-sm btn-link text-secondary p-0 me-2" type="button" onclick="document.getElementById('edit-<?= $n['id'] ?>').classList.toggle('d-none')"><i class="bi bi-pencil"></i></button>
     <form class="d-inline" method="post" action="<?= $base . '/' . $n['id'] . '/delete' ?>" onsubmit="return confirm('Delete this note?')"><?= csrf_field() ?><input type="hidden" name="return" value="<?= esc($returnUrl) ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>
    </span><?php endif ?>
   </div>
   <div class="mt-1" style="white-space:pre-wrap"><?= esc($n['body']) ?></div>
   <div class="small text-secondary mt-1">— <?= esc($n['author_name']) ?>, entered <?= fmt_date($n['created_at'], 'M j, Y g:i a') ?><?= $n['edited_at'] ? ', edited ' . fmt_date($n['edited_at'], 'M j, Y g:i a') : '' ?></div>
   <?php if ($mine): ?>
   <form class="row g-2 mt-1 d-none" id="edit-<?= $n['id'] ?>" method="post" action="<?= $base . '/' . $n['id'] ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= esc($returnUrl) ?>">
    <div class="col-sm-3"><input class="form-control form-control-sm" type="date" name="noted_on" value="<?= esc($n['noted_on']) ?>" required></div>
    <div class="col-sm-3"><input class="form-control form-control-sm" type="time" name="noted_time" value="<?= esc($n['noted_time'] ?? '') ?>"></div>
    <div class="col-sm-6"><select class="form-select form-select-sm" name="category"><?php foreach (NoteModel::CATEGORIES as $k => $l): ?><option value="<?= $k ?>" <?= $n['category'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
    <div class="col-12"><textarea class="form-control form-control-sm" name="body" rows="3" required><?= esc($n['body']) ?></textarea></div>
    <div class="col-12 text-end"><button class="btn btn-sm btn-outline-dark">Save changes</button></div>
   </form>
   <?php endif ?>
  </li>
 <?php endforeach ?>
 </ul>
</div>
