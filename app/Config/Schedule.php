<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Courses and the weekly schedule (the "LS & ST Map"). Lists live here, not in code.
 */
class Schedule extends BaseConfig
{
    /** Course pillars / aspects: key => [label, default colour]. */
    public array $pillars = [
        'bible'      => ['Bible',          '#6f42c1'],
        'worldview'  => ['Worldview',      '#0d6efd'],
        'leadership' => ['Leadership',     '#198754'],
        'lifeskills' => ['Life Skills',    '#fd7e14'],
        'special'    => ['Special Topic',  '#d63384'],
        'formation'  => ['Formation',      '#20c997'],
        'event'      => ['Event / Milestone', '#ffc107'],
        'break'      => ['Break / Off',    '#adb5bd'],
        'other'      => ['Other',          '#6c757d'],
    ];

    /** Life-skills categories (the legend across the top of the map). */
    public array $categories = ['Phys Ed', 'Outdoors', 'Automotive', 'Home', 'Creation Care', 'Artisanship', 'Vocation'];

    public array $audiences = ['coed' => 'Coed', 'guys' => 'Guys', 'girls' => 'Girls'];

    /** Roles a person can have on a course or a single session. */
    public array $staffRoles = ['instructor' => 'Instructor', 'co-teacher' => 'Co-teacher', 'assistant' => 'Assistant', 'helper' => 'Helper', 'sub' => 'Substitute', 'guest' => 'Guest speaker'];

    public array $statuses = ['planned' => 'Planned', 'confirmed' => 'Confirmed', 'done' => 'Done', 'missed' => 'Missed', 'cancelled' => 'Cancelled'];

    public array $weekdays = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
}
