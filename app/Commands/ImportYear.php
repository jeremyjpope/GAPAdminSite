<?php

namespace App\Commands;

use App\Models\GroupMemberModel;
use App\Models\GroupModel;
use App\Models\GroupSetModel;
use App\Models\HostHomeModel;
use App\Models\MentorModel;
use App\Models\PaymentModel;
use App\Models\ProgramYearModel;
use App\Models\StudentContactModel;
use App\Models\StudentModel;
use App\Models\StudentVehicleModel;
use App\Models\TuitionAccountModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark gap:import writable/import/year8.json [--year 2026-27]
 *
 * Loads the JSON produced by tools/excel_to_json.py into the program year.
 * Students are matched by email, then by first+last name; existing records are updated,
 * contacts/vehicles/payments are only added when an identical row is not already present.
 */
class ImportYear extends BaseCommand
{
    protected $group       = 'GAP';
    protected $name        = 'gap:import';
    protected $description = 'Import students, host homes, mentors and tuition from a JSON file made by tools/excel_to_json.py';
    protected $usage       = 'gap:import <file.json> [--year 2026-27]';

    public function run(array $params)
    {
        $file = $params[0] ?? null;
        if ($file && ! is_file($file) && is_file(ROOTPATH . $file)) {
            $file = ROOTPATH . $file;
        }
        if (! $file || ! is_file($file)) {
            CLI::error('Usage: php spark gap:import writable/import/year8.json [--year 2026-27]');

            return;
        }
        $data = json_decode(file_get_contents($file), true);
        if (! $data) {
            CLI::error('Could not parse JSON.');

            return;
        }

        $ym    = new ProgramYearModel();
        $label = CLI::getOption('year');
        $year  = $label ? $ym->where('label', $label)->first() : ($ym->where('is_current', 1)->first() ?? $ym->orderBy('id', 'DESC')->first());
        if (! $year) {
            CLI::error('No program year found. Run the InitialSeeder or create one in Site admin.');

            return;
        }
        CLI::write("Importing into program year {$year['label']} (id {$year['id']})", 'yellow');
        $yid = $year['id'];

        // Host homes
        $hm      = new HostHomeModel();
        $homeIds = [];
        foreach ($data['host_homes'] ?? [] as $h) {
            $row = $hm->where('program_year_id', $yid)->where('family_name', $h['family_name'])->first();
            $f   = ['program_year_id' => $yid, 'family_name' => $h['family_name'], 'address' => $h['address'] ?: null, 'email' => $h['email'] ?: null,
                    'email2' => $h['email2'] ?: null, 'phone' => $h['phone'] ?: null, 'phone2' => $h['phone2'] ?: null, 'notes' => $h['notes'] ?: null];
            if ($row) {
                $hm->update($row['id'], $f);
                $id = $row['id'];
            } else {
                $hm->insert($f);
                $id = $hm->getInsertID();
            }
            $homeIds[$h['key'] ?? $h['family_name']] = $id;
        }
        CLI::write('Host homes: ' . count($homeIds));

        // Mentors
        $mm        = new MentorModel();
        $mentorIds = [];
        foreach ($data['mentors'] ?? [] as $m) {
            $row = $mm->where('program_year_id', $yid)->where('name', $m['name'])->first();
            $f   = ['program_year_id' => $yid, 'name' => $m['name'], 'email' => $m['email'] ?: null, 'gender' => $m['gender'] ?: null];
            if ($row) {
                $mm->update($row['id'], $f);
                $mentorIds[$m['name']] = $row['id'];
            } else {
                $mm->insert($f);
                $mentorIds[$m['name']] = $mm->getInsertID();
            }
        }
        CLI::write('Mentors: ' . count($mentorIds));

        // Students
        $sm = new StudentModel();
        $cm = new StudentContactModel();
        $vm = new StudentVehicleModel();
        $tm = new TuitionAccountModel();
        $pm = new PaymentModel();
        $created = $updated = $payments = 0;
        foreach ($data['students'] ?? [] as $s) {
            $row = null;
            if (! empty($s['email'])) {
                $row = $sm->where('program_year_id', $yid)->where('LOWER(email)', strtolower($s['email']))->first();
            }
            $row ??= $sm->where('program_year_id', $yid)->where('LOWER(last_name)', strtolower($s['last_name']))
                ->groupStart()->where('LOWER(first_name)', strtolower($s['first_name']))->orWhere('LOWER(preferred_name)', strtolower($s['first_name']))->groupEnd()->first();

            $f = [
                'program_year_id' => $yid,
                'first_name' => $s['first_name'], 'last_name' => $s['last_name'], 'preferred_name' => $s['preferred_name'] ?: null,
                'email' => $s['email'] ?: null, 'phone' => $s['phone'] ?: null, 'gender' => $s['gender'] ?: null,
                'birthday' => $s['birthday'] ?: null, 'address' => $s['address'] ?: null, 'tshirt_size' => $s['tshirt_size'] ?: null,
                'placement_preference' => $s['placement_preference'] ?: null, 'allergies_medical' => $s['allergies_medical'] ?: null,
                'cleared_to_drive' => $s['cleared_to_drive'] ?: null, 'auto_insurance' => $s['auto_insurance'] ?: null,
                'housing_preference' => $s['housing_preference'] ?: null, 'notes' => $s['notes'] ?: null,
                'host_home_id' => $s['host_home'] ? ($homeIds[$s['host_home']] ?? null) : null,
                'mentor_id'    => $s['mentor'] ? ($mentorIds[$s['mentor']] ?? null) : null,
            ];
            if ($row) {
                $f = array_filter($f, fn ($v) => $v !== null) + ['host_home_id' => $f['host_home_id'], 'mentor_id' => $f['mentor_id']];
                $sm->update($row['id'], $f);
                $sid = $row['id'];
                $updated++;
            } else {
                $sm->insert($f);
                $sid = $sm->getInsertID();
                $created++;
            }

            foreach ($s['contacts'] ?? [] as $c) {
                if (! $cm->where('student_id', $sid)->where('name', $c['name'])->first()) {
                    $cm->insert(['student_id' => $sid, 'relation' => $c['relation'] ?? 'Parent', 'name' => $c['name'], 'email' => $c['email'] ?: null, 'phone' => $c['phone'] ?: null]);
                }
            }
            foreach ($s['vehicles'] ?? [] as $v) {
                if (! $vm->where('student_id', $sid)->where('description', $v['description'])->first()) {
                    $vm->insert(['student_id' => $sid, 'description' => $v['description'], 'vin' => $v['vin'] ?: null, 'oil_type' => $v['oil_type'] ?: null, 'oil_notes' => $v['oil_notes'] ?: null]);
                }
            }
            if (! empty($s['tuition'])) {
                $t    = $s['tuition'];
                $acct = $tm->where('student_id', $sid)->first();
                $tf   = ['student_id' => $sid, 'plan_type' => $t['plan_type'], 'total_due' => $t['total_due'], 'notes' => $t['notes'] ?: null];
                $acct ? $tm->update($acct['id'], $tf) : $tm->insert($tf);
                foreach ($t['payments'] ?? [] as $p) {
                    $dup = $pm->where('student_id', $sid)->where('paid_on', $p['paid_on'])->where('amount', $p['amount'])->first();
                    if (! $dup) {
                        $pm->insert(['student_id' => $sid, 'paid_on' => $p['paid_on'], 'amount' => $p['amount'], 'method' => $p['method'] ?: null, 'reference' => $p['reference'] ?: null, 'note' => $p['note'] ?: null]);
                        $payments++;
                    }
                }
            }
        }
        CLI::write("Students: {$created} created, {$updated} updated. Payments added: {$payments}", 'green');

        // Group sets: matched by name within the year; membership is replaced from the file.
        $gsm = new GroupSetModel();
        $gm  = new GroupModel();
        $mm  = new GroupMemberModel();
        $byName = [];
        foreach ($sm->where('program_year_id', $yid)->findAll() as $st) {
            $byName[strtolower($st['first_name'] . ' ' . $st['last_name'])] = $st['id'];
        }
        $sort = 0;
        foreach ($data['group_sets'] ?? [] as $gs) {
            $sort += 10;
            $set = $gsm->where('program_year_id', $yid)->where('name', $gs['name'])->first();
            $sf  = ['program_year_id' => $yid, 'name' => $gs['name'], 'kind' => $gs['kind'] ?? 'group', 'group_label' => $gs['group_label'] ?? 'Group', 'sort_order' => $sort];
            if ($set) {
                $gsm->update($set['id'], $sf);
                $setId = $set['id'];
            } else {
                $gsm->insert($sf);
                $setId = $gsm->getInsertID();
            }
            $n = 0;
            foreach ($gs['groups'] ?? [] as $i => $g) {
                $row = $gm->where('group_set_id', $setId)->where('name', $g['name'])->first();
                $gf  = ['group_set_id' => $setId, 'name' => $g['name'], 'sort_order' => ($i + 1) * 10];
                if ($row) {
                    $gm->update($row['id'], $gf);
                    $gid = $row['id'];
                } else {
                    $gm->insert($gf);
                    $gid = $gm->getInsertID();
                }
                $mm->where('group_id', $gid)->delete();
                foreach ($g['members'] ?? [] as $name) {
                    $sid = $byName[strtolower($name)] ?? null;
                    if ($sid) {
                        $mm->insert(['group_id' => $gid, 'student_id' => $sid]);
                        $n++;
                    } else {
                        CLI::write("  unmatched member '{$name}' in {$gs['name']} / {$g['name']}", 'yellow');
                    }
                }
            }
            CLI::write("Group set '{$gs['name']}': " . count($gs['groups'] ?? []) . " groups, {$n} members");
        }
    }
}
