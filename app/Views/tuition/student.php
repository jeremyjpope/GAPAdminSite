<?= $this->extend('layouts/main') ?>
<?= $this->section('actions') ?><?php if (! empty($acct['mp_link'])): ?><a class="btn btn-sm btn-success" href="<?= esc($acct['mp_link']) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Payment plan in Ministry Platform</a> <?php endif ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('tuition') ?>">All students</a> <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('students/' . $s['id']) ?>">Student record</a><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $due = (float) ($acct['total_due'] ?? 0); ?>
<div class="row g-3">
 <div class="col-lg-4">
  <div class="card mb-3"><div class="card-header bg-white fw-semibold">Plan</div><div class="card-body">
   <form method="post" action="<?= site_url('tuition/' . $s['id'] . '/account') ?>"><?= csrf_field() ?>
    <div class="mb-2"><label class="form-label">Plan type</label><select class="form-select" name="plan_type" id="plan_type">
      <?php foreach (['year' => 'Year in full', 'semester' => 'By semester', 'month' => 'Monthly (10 months)', 'other' => 'Other / custom'] as $k => $l): ?><option value="<?= $k ?>" <?= ($acct['plan_type'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
    <div class="mb-2"><label class="form-label">Total due for the year</label><div class="input-group"><span class="input-group-text">$</span><input class="form-control" name="total_due" id="total_due" value="<?= esc($acct['total_due'] ?? $year['tuition_year_early']) ?>"></div>
      <div class="form-text">Early: <?= money($year['tuition_year_early']) ?> · Late: <?= money($year['tuition_year_late']) ?> <a href="#" onclick="document.getElementById('total_due').value='<?= (float) $year['tuition_year_early'] ?>';return false">use early</a> · <a href="#" onclick="document.getElementById('total_due').value='<?= (float) $year['tuition_year_late'] ?>';return false">use late</a></div></div>
    <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3" placeholder="Who pays, link sent dates, post-dated checks…"><?= esc($acct['notes'] ?? '') ?></textarea></div>
    <div class="mb-2"><label class="form-label">Ministry Platform payment plan link</label><input class="form-control" name="mp_link" value="<?= esc($acct['mp_link'] ?? '') ?>" placeholder="https://…"><div class="form-text">Paste the URL of this student's payment plan in MP; it shows as a button on this page and a link on the Tuition list.</div></div>
    <button class="btn btn-dark btn-sm">Save plan</button>
   </form>
  </div></div>
  <div class="card"><div class="card-body">
   <div class="d-flex justify-content-between"><span>Total due</span><strong><?= money($due) ?></strong></div>
   <div class="d-flex justify-content-between"><span>Received</span><strong><?= money($paid) ?></strong></div>
   <?php if ($scheduled): ?><div class="d-flex justify-content-between text-secondary"><span>Post-dated (future)</span><span><?= money($scheduled) ?></span></div><?php endif ?>
   <hr class="my-2"><div class="d-flex justify-content-between fs-5"><span>Balance</span><strong class="<?= $due - $paid - $scheduled <= 0 && $acct ? 'text-success' : 'text-danger' ?>"><?= money($due - $paid - $scheduled) ?></strong></div>
  </div></div>
 </div>
 <div class="col-lg-8">
  <div class="card"><div class="card-header bg-white fw-semibold">Payments</div>
   <table class="table table-sm mb-0"><thead class="table-light"><tr><th>Date</th><th class="text-end">Amount</th><th>Method</th><th>Reference</th><th>Note</th><th></th></tr></thead><tbody>
   <?php if (! $payments): ?><tr><td colspan="6" class="text-secondary p-3">No payments yet.</td></tr><?php endif ?>
   <?php foreach ($payments as $p): $future = $p['paid_on'] > date('Y-m-d'); ?><tr class="<?= $future ? 'text-secondary' : '' ?>"><td><?= fmt_date($p['paid_on']) ?><?= $future ? ' <span class="badge text-bg-light">post-dated</span>' : '' ?></td><td class="text-end"><?= money($p['amount']) ?></td><td><?= esc($p['method']) ?></td><td><?= esc($p['reference']) ?></td><td class="small text-secondary"><?= esc($p['note']) ?></td>
     <td class="text-end"><form method="post" action="<?= site_url('tuition/' . $s['id'] . '/payments/' . $p['id'] . '/delete') ?>" onsubmit="return confirm('Delete this payment?')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form></td></tr><?php endforeach ?>
   </tbody></table>
   <form class="card-body border-top row g-2 align-items-end" method="post" action="<?= site_url('tuition/' . $s['id'] . '/payments') ?>"><?= csrf_field() ?>
    <div class="col-md-2"><label class="form-label">Date</label><input class="form-control form-control-sm" type="date" name="paid_on" value="<?= date('Y-m-d') ?>"></div>
    <div class="col-md-2"><label class="form-label">Amount</label><input class="form-control form-control-sm" name="amount" placeholder="400" required></div>
    <div class="col-md-2"><label class="form-label">Method</label><select class="form-select form-select-sm" name="method"><option>check</option><option>online</option><option>cash</option><option>other</option></select></div>
    <div class="col-md-2"><label class="form-label">Reference</label><input class="form-control form-control-sm" name="reference" placeholder="ck #1089"></div>
    <div class="col-md-3"><label class="form-label">Note</label><input class="form-control form-control-sm" name="note" placeholder="parents' acct"></div>
    <div class="col-md-1"><button class="btn btn-sm btn-dark w-100">Add</button></div>
   </form>
  </div>
 </div>
</div>
<?= $this->endSection() ?>
