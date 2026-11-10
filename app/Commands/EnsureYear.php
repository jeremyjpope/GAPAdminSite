<?php

namespace App\Commands;

use App\Models\ProgramYearModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark gap:year 2024-25 --number 6 [--early 3500 --late 3500]
 * Creates the program year if it does not exist (dates default to Aug 1 – May 31). Idempotent.
 */
class EnsureYear extends BaseCommand
{
    protected $group       = 'GAP';
    protected $name        = 'gap:year';
    protected $description = 'Create a program year (label like 2024-25) if it is missing';
    protected $usage       = 'gap:year <label> --number N [--early amount] [--late amount]';

    public function run(array $params)
    {
        $label = $params[0] ?? '';
        if (! preg_match('/^(\d{4})-(\d{2})$/', $label, $m)) {
            CLI::error('Label must look like 2024-25');

            return;
        }
        $pm = new ProgramYearModel();
        if ($row = $pm->where('label', $label)->first()) {
            CLI::write("Program year {$label} already exists (id {$row['id']})");

            return;
        }
        $tmpl  = $pm->orderBy('year_number', 'DESC')->first();
        $start = (int) $m[1];
        $data  = [
            'label' => $label, 'year_number' => (int) (CLI::getOption('number') ?: 0),
            'start_date' => "{$start}-08-01", 'end_date' => ($start + 1) . '-05-31',
            'tuition_year_early' => CLI::getOption('early') ?: null, 'tuition_year_late' => CLI::getOption('late') ?: CLI::getOption('early') ?: null,
            'program_description' => $tmpl['program_description'] ?? 'Nine-month, full-time discipleship program',
            'transcript_notes' => $tmpl['transcript_notes'] ?? null, 'grade_scale' => $tmpl['grade_scale'] ?? null, 'is_current' => 0,
        ];
        $pm->insert($data);
        CLI::write("Created program year {$label} (id {$pm->getInsertID()}); adjust dates/tuition in Site admin > Program years", 'green');
    }
}
