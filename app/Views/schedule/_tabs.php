<?php $p = trim(uri_string(), '/'); $tabs = [['schedule', 'Week', 'calendar-week'], ['schedule/year', 'Year map', 'calendar3'], ['schedule/grid', 'Year grid', 'grid-3x3'], ['schedule/hours', 'Hours', 'clock'], ['schedule/staff', 'Staff', 'person-badge'], ['schedule/recurring', 'Standing week', 'arrow-repeat'], ['schedule/settings', 'Slots & blocks', 'sliders']]; ?>
<ul class="nav nav-pills mb-3 small d-print-none">
<?php foreach ($tabs as [$href, $label, $icon]): $act = $href === 'schedule' ? in_array($p, ['schedule', 'schedule/week'], true) : str_starts_with($p, $href); ?>
 <li class="nav-item"><a class="nav-link py-1 <?= $act ? 'active bg-dark' : 'text-dark' ?>" href="<?= site_url($href) ?>"><i class="bi bi-<?= $icon ?> me-1"></i><?= $label ?></a></li>
<?php endforeach ?>
</ul>
