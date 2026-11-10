<?php

namespace App\Commands;

use App\Models\ApplicantModel;
use App\Models\ApplicantStepModel;
use App\Models\ProgramYearModel;
use App\Models\StudentModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark gap:import-applicants writable/import/applicants2627.json --year 2026-27
 *
 * Loads one year's applicant tab(s) (converted by tools/applicants_to_json.py, format in
 * docs/applicants_import.md). Applicants are matched by email then name within the year, so the
 * import can be re-run; sheet values fill blanks only. Anyone matching a student of that year is
 * linked to it and marked accepted. Earlier applications by the same person are linked.
 */
class ImportApplicants extends BaseCommand
{
    protected $group       = 'GAP';
    protected $name        = 'gap:import-applicants';
    protected $description = 'Import a year\'s applicants JSON';
    protected $usage       = 'gap:import-applicants <json path> --year <label>';

    public function run(array $params)
    {
        $file = $params[0] ?? '';
        if ($file && ! is_file($file) && is_file(ROOTPATH . $file)) {
            $file = ROOTPATH . $file;
        }
        if (! $file || ! is_file($file)) {
            CLI::error('Usage: gap:import-applicants <json> --year <label>');

            return;
        }
        $data  = json_decode(file_get_contents($file), true);
        $label = CLI::getOption('year') ?: ($data['label'] ?? '');
        $year  = (new ProgramYearModel())->where('label', $label)->first();
        if (! is_array($data) || ! $year) {
            CLI::error('Bad JSON or unknown program year ' . $label);

            return;
        }
        $yid = $year['id'];
        CLI::write("Importing applicants into {$label} (id {$yid})", 'yellow');

        $am  = new ApplicantModel();
        $stm = new ApplicantStepModel();
        $students = (new StudentModel())->where('program_year_id', $yid)->findAll();
        $byEmail = $byName = [];
        foreach ($students as $s) {
            if ($s['email']) {
                $byEmail[strtolower(trim($s['email']))] = $s;
            }
            $byName[strtolower($s['first_name'] . ' ' . $s['last_name'])] = $s;
            if ($s['preferred_name']) {
                $byName[strtolower($s['preferred_name'] . ' ' . $s['last_name'])] = $s;
            }
        }
        $fields  = ['submission_order', 'first_name', 'last_name', 'preferred_name', 'gender', 'email', 'phone', 'address', 'birthday', 'mom_email', 'dad_email',
            'tshirt_size', 'placement_preference', 'allergies_medical', 'cleared_to_drive', 'auto_insurance', 'housing_preference', 'tuition_note', 'notes'];
        $taken = [];   // student_id => applicant id already linked (a student belongs to one applicant)
        foreach ($am->where('program_year_id', $yid)->where('student_id IS NOT NULL')->findAll() as $r) {
            $taken[$r['student_id']] = $r['id'];
        }
        $created = $updated = $linked = $stepsN = 0;
        $statuses = [];
        $idsByName = [];
        // match each applicant to a student of the year: an exact name match outranks an email match
        // (siblings sometimes share a family email), so name matches are processed first
        $queue = [];
        foreach ($data['applicants'] ?? [] as $a) {
            $email   = strtolower(trim((string) ($a['email'] ?? '')));
            $byN     = $byName[strtolower($a['first_name'] . ' ' . $a['last_name'])] ?? $byName[strtolower(($a['preferred_name'] ?? '') . ' ' . $a['last_name'])] ?? null;
            $student = $byN ?? (($email && isset($byEmail[$email])) ? $byEmail[$email] : null);
            $queue[] = [$a, $email, $student, $byN ? 0 : 1];
        }
        usort($queue, static fn ($x, $y) => $x[3] <=> $y[3]);
        foreach ($queue as [$a, $email, $student]) {
            $row   = $email ? $am->where('program_year_id', $yid)->where('LOWER(email)', $email)->first() : null;
            $row ??= $am->where('program_year_id', $yid)->where('LOWER(first_name)', strtolower($a['first_name']))->where('LOWER(last_name)', strtolower($a['last_name']))->first();
            $f = ['program_year_id' => $yid];
            foreach ($fields as $k) {
                $v     = $a[$k] ?? null;
                $f[$k] = ($v === '' || $v === null) ? null : $v;
            }
            if ($student && isset($taken[$student['id']]) && (! $row || $taken[$student['id']] !== $row['id'])) {
                CLI::write("  {$a['first_name']} {$a['last_name']}: student {$student['first_name']} {$student['last_name']} is already linked to another applicant; not linked", 'yellow');
                $student = null;
            }
            $status = $student ? 'accepted' : (in_array($a['status'] ?? '', ['accepted', 'declined', 'waitlisted', 'withdrawn'], true) ? $a['status'] : 'withdrawn');
            if ($status === 'accepted' && ! $student) {
                $status = 'withdrawn';   // accepted per the sheet but never became a student
                CLI::write("  {$a['first_name']} {$a['last_name']} looked accepted but is not a {$label} student; marked withdrawn", 'yellow');
            }
            if ($row) {
                $upd = [];
                foreach ($f as $k => $v) {
                    if ($v !== null && ($row[$k] === null || $row[$k] === '')) {
                        $upd[$k] = $v;
                    }
                }
                if ($row['status'] === 'applied' || $student) {
                    $upd['status'] = $status;
                }
                if ($student && ! $row['student_id']) {
                    $upd['student_id'] = $student['id'];
                }
                if ($upd) {
                    $am->update($row['id'], $upd);
                }
                $id = $row['id'];
                $updated++;
            } else {
                $f['status']     = $status;
                $f['student_id'] = $student['id'] ?? null;
                // earlier application by the same person (any year before this one)
                $earlier = array_column(array_filter(all_years(), static fn ($y) => (int) $y['year_number'] < (int) $year['year_number']), 'id') ?: [0];
                $prior = $email ? $am->where('LOWER(email)', $email)->whereIn('program_year_id', $earlier)->orderBy('id', 'DESC')->first() : null;
                $prior ??= $am->where('LOWER(first_name)', strtolower($a['first_name']))->where('LOWER(last_name)', strtolower($a['last_name']))->whereIn('program_year_id', $earlier)->orderBy('id', 'DESC')->first();
                $f['prior_applicant_id'] = $prior['id'] ?? null;
                $am->insert($f);
                $id = $am->getInsertID();
                $created++;
            }
            if ($student) {
                $linked++;
                $taken[$student['id']] = $id;
            }
            $idsByName[strtolower($a['first_name'] . ' ' . $a['last_name'])] = $id;
            if (! empty($a['preferred_name'])) {
                $idsByName[strtolower($a['preferred_name'] . ' ' . $a['last_name'])] = $id;
            }
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            foreach ($a['steps'] ?? [] as $step => $s) {
                if ($stm->where('applicant_id', $id)->where('step', $step)->first()) {
                    continue;
                }
                $done = ! empty($s['done']) || ! empty($s['done_on']);
                $note = trim((string) ($s['note'] ?? '')) ?: null;
                if (! $done && ! $note) {
                    continue;
                }
                $stm->insert(['applicant_id' => $id, 'step' => $step, 'done' => $done ? 1 : 0, 'done_on' => $s['done_on'] ?? null, 'note' => $note]);
                $stepsN++;
            }
        }
        // interview rubric scores from the sheet, keyed by scorer first name (no site user)
        $scm = new \App\Models\ApplicantScoreModel();
        $scoresN = 0;
        foreach ($data['rubric'] ?? [] as $r) {
            $aid = $idsByName[strtolower($r['first_name'] . ' ' . $r['last_name'])] ?? null;
            if (! $aid) {
                CLI::write("  rubric: no applicant named {$r['first_name']} {$r['last_name']}", 'yellow');
                continue;
            }
            foreach ($r['scores'] ?? [] as $cat => $byScorer) {
                foreach ($byScorer as $name => $score) {
                    if ($scm->where('applicant_id', $aid)->where('scorer_name', $name)->where('category', $cat)->first()) {
                        continue;
                    }
                    $scm->insert(['applicant_id' => $aid, 'user_id' => null, 'scorer_name' => $name, 'category' => $cat, 'score' => (int) $score, 'updated_at' => date('Y-m-d H:i:s')]);
                    $scoresN++;
                }
            }
        }
        CLI::write("Applicants: {$created} created, {$updated} updated, {$linked} linked to students; {$stepsN} checklist steps; {$scoresN} rubric scores");
        CLI::write('Statuses: ' . json_encode($statuses));
        foreach ($data['warnings'] ?? [] as $w) {
            CLI::write('  parser: ' . $w, 'yellow');
        }
    }
}
