<?php

use App\Models\ProgramYearModel;

/**
 * Current program year (selected in session, else the one flagged is_current, else the newest).
 */
function current_year(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $m  = new ProgramYearModel();
    $id = session()->get('year_id');
    $y  = $id ? $m->find($id) : null;
    $y ??= $m->where('is_current', 1)->first();
    $y ??= $m->orderBy('id', 'DESC')->first();

    return $cache = $y;
}

function all_years(): array
{
    return (new ProgramYearModel())->orderBy('year_number', 'DESC')->findAll();
}

function student_name(array $s): string
{
    $first = $s['preferred_name'] ?: $s['first_name'];

    return trim($first . ' ' . $s['last_name']);
}

function money(?float $v): string
{
    return '$' . number_format((float) $v, 2);
}

function fmt_date(?string $d, string $fmt = 'M j, Y'): string
{
    if (! $d) {
        return '';
    }
    $t = strtotime($d);

    return $t ? date($fmt, $t) : $d;
}

/** Convert a percent to a letter using the year's grade scale JSON. */
function letter_for(?float $percent, array $year): string
{
    if ($percent === null) {
        return '';
    }
    $scale = json_decode($year['grade_scale'] ?? '[]', true) ?: [];
    usort($scale, fn ($a, $b) => $b['min'] <=> $a['min']);
    foreach ($scale as $row) {
        if ($percent >= $row['min']) {
            return $row['letter'];
        }
    }

    return $scale ? end($scale)['letter'] : '';
}

function points_for(string $letter, array $year): ?float
{
    $scale = json_decode($year['grade_scale'] ?? '[]', true) ?: [];
    foreach ($scale as $row) {
        if (strcasecmp($row['letter'], $letter) === 0) {
            return (float) $row['points'];
        }
    }

    return null;
}

function flash_alerts(): string
{
    $out = '';
    foreach (['error' => 'danger', 'success' => 'success', 'info' => 'info', 'warning' => 'warning'] as $k => $cls) {
        if ($msg = session()->getFlashdata($k)) {
            $out .= '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">' . esc($msg)
                  . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }

    return $out;
}
