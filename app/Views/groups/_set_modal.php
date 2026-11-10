<div class="modal fade" id="setModal"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= site_url('groups/sets') ?>" id="setForm"><?= csrf_field() ?><input type="hidden" name="id">
 <div class="modal-header"><h5 class="modal-title">Group set</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body row g-2">
  <div class="col-12"><label class="form-label">Name</label><input class="form-control" name="name" placeholder="Friday Night Groups" required></div>
  <div class="col-6"><label class="form-label">Type</label><select class="form-select" name="kind"><option value="group">Groups</option><option value="partners">Partners / pairs</option></select></div>
  <div class="col-6"><label class="form-label">What a group is called</label><input class="form-control" name="group_label" placeholder="Group / Team / Pair" value="Group"></div>
  <div class="col-4"><label class="form-label">Sort</label><input class="form-control" name="sort_order" type="number" value="0"></div>
  <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
 </div><div class="modal-footer"><button class="btn btn-dark">Save</button></div></form></div></div>
<script>function editSet(s){const f=document.getElementById('setForm');for(const k of ['id','name','kind','group_label','sort_order','notes'])f.elements[k].value=s?(s[k]??''):(k==='group_label'?'Group':k==='kind'?'group':k==='sort_order'?'0':'');}</script>
