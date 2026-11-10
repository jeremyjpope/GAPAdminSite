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

/** Colour for a course row (its own colour, else its pillar's). */
function course_color(array $course): string
{
    return $course['color'] ?: ((new \Config\Schedule())->pillars[$course['pillar']][1] ?? '#6c757d');
}

/** Hours of one schedule entry per day it covers: the slot length (summing spanned slots); 0 for all-day entries. */
function schedule_entry_hours(array $e, array $slotsById): float
{
    if (! $e['slot_id'] || ! isset($slotsById[$e['slot_id']])) {
        return 0.0;
    }
    $ordered = array_values($slotsById);
    usort($ordered, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order'] ?: $a['id'] <=> $b['id']);
    $i = array_search($e['slot_id'], array_column($ordered, 'id'), false);
    $h = 0.0;
    for ($k = 0; $k < max(1, (int) $e['slot_span']); $k++) {
        $s = $ordered[$i + $k] ?? null;
        if ($s && $s['start_time'] && $s['end_time']) {
            $h += (strtotime('1970-01-01 ' . $s['end_time']) - strtotime('1970-01-01 ' . $s['start_time'])) / 3600;
        }
    }
    $days = 0;
    for ($d = strtotime($e['start_date']); $d <= strtotime($e['end_date']); $d += 86400) {
        $days++;
    }

    return $h * max(1, $days);
}

/** GAP week number for a date, from the year's week_one_starts (null if not set or before week 1). */
function gap_week(string $date, array $year): ?int
{
    if (empty($year['week_one_starts'])) {
        return null;
    }
    $n = intdiv((int) ((strtotime($date) - strtotime($year['week_one_starts'])) / 86400), 7) + 1;

    return $n >= 1 ? $n : null;
}

/** "7:30-9:00" style label for a slot. */
function slot_time(array $slot): string
{
    $f = fn ($t) => $t ? ltrim(date('g:i', strtotime('1970-01-01 ' . $t)), '0') : '';
    if (! $slot['start_time'] && ! $slot['end_time']) {
        return '';
    }

    return $f($slot['start_time']) . '–' . $f($slot['end_time']);
}
