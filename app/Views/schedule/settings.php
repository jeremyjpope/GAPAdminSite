<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= $this->include('schedule/_tabs') ?>
<div class="row g-3">
 <div class="col-lg-6">
  <div class="card mb-3"><div class="card-header bg-white fw-semibold">Time slots for <?= esc($year['label']) ?> <span class="small text-secondary fw-normal">the columns of the map</span></div>
   <table class="table table-sm mb-0 align-middle"><thead class="table-light"><tr><th>#</th><th>Label</th><th>Audience</th><th>Time</th><th></th></tr></thead><tbody>
   <?php foreach ($slots as $s): ?><tr><td><?= $s['sort_order'] ?></td><td><?= esc($s['label']) ?></td><td><?= $cfg->audiences[$s['audience']] ?? '' ?></td><td><?= esc(slot_time($s)) ?></td>
    <td class="text-end text-nowrap"><button class="btn btn-sm btn-link p-0 me-2" onclick='editS(<?= json_encode($s) ?>)'><i class="bi bi-pencil"></i></button><form class="d-inline" method="post" action="<?= site_url('schedule/settings/slots/' . $s['id'] . '/delete') ?>" onsubmit="return confirm('Remove this slot?')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form></td></tr><?php endforeach ?>
   <?php if (! $slots): ?><tr><td colspan="5" class="text-secondary small">No slots yet.<?php if ($prev): ?> <form class="d-inline" method="post" action="<?= site_url('schedule/settings/copy') ?>"><?= csrf_field() ?><input type="hidden" name="from" value="<?= $prev['id'] ?>"><button class="btn btn-sm btn-outline-dark">Copy from <?= esc($prev['label']) ?></button></form><?php endif ?></td></tr><?php endif ?>
   </tbody></table>
   <form class="card-footer bg-white row g-2" method="post" action="<?= site_url('schedule/settings/slots') ?>" id="sForm"><?= csrf_field() ?><input type="hidden" name="id">
    <div class="col-1"><input class="form-control form-control-sm" name="sort_order" placeholder="#" type="number"></div>
    <div class="col-4"><input class="form-control form-control-sm" name="label" placeholder="Coed Morning" required></div>
    <div class="col-2"><select class="form-select form-select-sm" name="audience"><?php foreach ($cfg->audiences as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach ?></select></div>
    <div class="col-2"><input class="form-control form-control-sm" type="time" name="start_time"></div>
    <div class="col-2"><input class="form-control form-control-sm" type="time" name="end_time"></div>
    <div class="col-1"><button class="btn btn-sm btn-dark w-100">Save</button></div>
   </form></div>
  <div class="small text-secondary">GAP week 1 starts on <strong><?= $year['week_one_starts'] ? fmt_date($year['week_one_starts']) : 'not set' ?></strong> — change it under <a href="<?= site_url('admin/years') ?>">Program years</a>.</div>
 </div>
 <div class="col-lg-6">
  <div class="card mb-3"><div class="card-header bg-white fw-semibold">Blocks <span class="small text-secondary fw-normal">the monthly Leadership topics and other phases down the left of the map</span></div>
   <table class="table table-sm mb-0 align-middle"><thead class="table-light"><tr><th>Label</th><th>From</th><th>To</th><th></th></tr></thead><tbody>
   <?php foreach ($blocks as $b): ?><tr><td><span class="badge" style="background:<?= esc($b['color'] ?: '#6c757d') ?>"><?= esc($b['label']) ?></span></td><td><?= fmt_date($b['start_date']) ?></td><td><?= fmt_date($b['end_date']) ?></td>
    <td class="text-end text-nowrap"><button class="btn btn-sm btn-link p-0 me-2" onclick='editB(<?= json_encode($b) ?>)'><i class="bi bi-pencil"></i></button><form class="d-inline" method="post" action="<?= site_url('schedule/settings/blocks/' . $b['id'] . '/delete') ?>" onsubmit="return confirm('Remove this block?')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form></td></tr><?php endforeach ?>
   <?php if (! $blocks): ?><tr><td colspan="4" class="text-secondary small">No blocks yet.</td></tr><?php endif ?>
   </tbody></table>
   <form class="card-footer bg-white row g-2" method="post" action="<?= site_url('schedule/settings/blocks') ?>" id="bForm"><?= csrf_field() ?><input type="hidden" name="id">
    <div class="col-4"><input class="form-control form-control-sm" name="label" placeholder="Leadership" required></div>
    <div class="col-3"><input class="form-control form-control-sm" type="date" name="start_date" required></div>
    <div class="col-3"><input class="form-control form-control-sm" type="date" name="end_date"></div>
    <div class="col-1"><input class="form-control form-control-sm form-control-color" type="color" name="color" value="#e9ecef"></div>
    <div class="col-1"><button class="btn btn-sm btn-dark w-100">Save</button></div>
   </form></div>
 </div>
</div>
<script>
function editS(s){const f=document.getElementById('sForm');for(const k of ['id','sort_order','label','audience','start_time','end_time'])f.elements[k].value=s[k]??'';f.elements['label'].focus();}
function editB(b){const f=document.getElementById('bForm');for(const k of ['id','label','start_date','end_date'])f.elements[k].value=b[k]??'';f.elements['color'].value=b.color||'#e9ecef';f.elements['label'].focus();}
</script>
<?= $this->endSection() ?>
