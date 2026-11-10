<?php

namespace App\Commands;

use App\Models\CourseModel;
use App\Models\CourseStaffModel;
use App\Models\EntryStaffModel;
use App\Models\GroupModel;
use App\Models\GroupSetModel;
use App\Models\ProgramYearModel;
use App\Models\ScheduleBlockModel;
use App\Models\ScheduleEntryModel;
use App\Models\ScheduleSlotModel;
use App\Models\VolunteerModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark gap:import-schedule writable/import/map2627.json --year 2026-27
 *
 * Loads one "LS & ST Map" tab (converted by tools/map_to_json.py, format in docs/schedule_import.md)
 * into the schedule: slots and blocks for the year (only when the year has none), courses matched
 * by name or created, sessions, and session staff matched to the volunteer list. Idempotent: a
 * session already present with the same dates, slot, course and detail is skipped.
 */
class ImportSchedule extends BaseCommand
{
    protected $group       = 'GAP';
    protected $name        = 'gap:import-schedule';
    protected $description = 'Import a yearly class map JSON into the schedule';
    protected $usage       = 'gap:import-schedule <json path> --year <label> [--replace]';

    public function run(array $params)
    {
        $file = $params[0] ?? '';
        if ($file && ! is_file($file) && is_file(ROOTPATH . $file)) {
            $file = ROOTPATH . $file;
        }
        if (! $file || ! is_file($file)) {
            CLI::error('Usage: gap:import-schedule <json> --year <label>');

            return;
        }
        $data = json_decode(file_get_contents($file), true);
        if (! is_array($data)) {
            CLI::error('Could not parse JSON');

            return;
        }
        $label = CLI::getOption('year') ?: ($data['label'] ?? '');
        $year  = (new ProgramYearModel())->where('label', $label)->first();
        if (! $year) {
            CLI::error("Program year {$label} not found (php spark gap:year {$label} --number N)");

            return;
        }
        $yid = $year['id'];
        CLI::write("Importing schedule into {$year['label']} (id {$yid})", 'yellow');

        $em = new ScheduleEntryModel();
        if (CLI::getOption('replace') !== null) {
            $ids = array_column($em->where('program_year_id', $yid)->findAll(), 'id');
            if ($ids) {
                (new EntryStaffModel())->whereIn('entry_id', $ids)->delete();
                $em->whereIn('id', $ids)->delete();
            }
            CLI::write('Removed ' . count($ids) . ' existing sessions (--replace).');
        }

        // week 1
        if (! empty($data['week_one_starts']) && empty($year['week_one_starts'])) {
            (new ProgramYearModel())->update($yid, ['week_one_starts' => $data['week_one_starts']]);
            CLI::write('GAP week 1 starts ' . $data['week_one_starts']);
        }

        // slots (keyed by the sheet column letter)
        $sm      = new ScheduleSlotModel();
        $slotIds = [];
        $existing = $sm->where('program_year_id', $yid)->orderBy('sort_order')->findAll();
        if ($existing) {
            // match by label when the year already has slots
            foreach ($data['slots'] ?? [] as $s) {
                foreach ($existing as $e) {
                    if (strcasecmp(trim($e['label']), trim($s['label'])) === 0) {
                        $slotIds[$s['key']] = $e['id'];
                    }
                }
                if (! isset($slotIds[$s['key']])) {
                    CLI::write("  slot '{$s['label']}' not found in this year's slots; its sessions will be skipped", 'red');
                }
            }
        } else {
            foreach ($data['slots'] ?? [] as $i => $s) {
                $sm->insert(['program_year_id' => $yid, 'label' => $s['label'], 'audience' => $s['audience'] ?? 'coed', 'start_time' => $s['start_time'] ?? null, 'end_time' => $s['end_time'] ?? null, 'sort_order' => $i + 1]);
                $slotIds[$s['key']] = $sm->getInsertID();
            }
            CLI::write('Slots: ' . count($slotIds) . ' created');
        }
        $slotOrder = array_values($slotIds);

        // blocks
        $bm = new ScheduleBlockModel();
        if (! $bm->where('program_year_id', $yid)->countAllResults()) {
            $palette = ['#fff2cc', '#d9ead3', '#cfe2f3', '#f4cccc', '#d9d2e9', '#fce5cd', '#ead1dc', '#c9daf8', '#e6e6e6', '#fff2cc', '#d9ead3'];
            foreach ($data['blocks'] ?? [] as $i => $b) {
                $bm->insert(['program_year_id' => $yid, 'label' => $b['label'], 'start_date' => $b['start_date'], 'end_date' => $b['end_date'], 'color' => $palette[$i % count($palette)]]);
            }
            CLI::write('Blocks: ' . count($data['blocks'] ?? []) . ' created');
        }

        // groups of this year, by letter, for linking "Group A" etc. (life-skills sets first)
        $groupIds = [];
        foreach ((new GroupSetModel())->where('program_year_id', $yid)->orderBy('sort_order')->findAll() as $set) {
            $isLs = stripos($set['name'], 'life skill') !== false;
            foreach ((new GroupModel())->where('group_set_id', $set['id'])->findAll() as $g) {
                $k = strtoupper(trim($g['name']));
                if (strlen($k) === 1 && ($isLs || ! isset($groupIds[$k]))) {
                    $groupIds[$k] = $g['id'];
                }
            }
        }

        // courses + volunteers
        $cm       = new CourseModel();
        $courses  = [];
        foreach ($cm->findAll() as $c) {
            $courses[strtolower(trim($c['name']))] = $c;
        }
        $vm       = new VolunteerModel();
        $byName   = [];
        $byLast   = [];
        foreach ($vm->findAll() as $v) {
            $byName[strtolower(trim($v['first_name'] . ' ' . $v['last_name']))] = $v['id'];
            $byLast[strtolower(trim($v['last_name']))][] = $v;
        }
        $byFirst = [];
        foreach ($vm->findAll() as $v) {
            $byFirst[strtolower(trim($v['first_name']))][] = $v['id'];
        }
        $findVol = static function (string $name) use ($byName, $byLast, $byFirst): ?int {
            $n = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
            if (isset($byName[$n])) {
                return $byName[$n];
            }
            $parts = explode(' ', $n);
            if (count($parts) === 1) {
                // a bare first name matches only when exactly one volunteer has it
                return count($byFirst[$n] ?? []) === 1 ? $byFirst[$n][0] : null;
            }
            $first = $parts[0];
            $last  = end($parts);
            $firstOk = static fn (string $vf) => $vf === $first || str_starts_with($vf, $first) || str_starts_with($first, $vf)
                || (strlen($first) >= 3 && strlen($vf) >= 3 && substr($vf, 0, 3) === substr($first, 0, 3));
            foreach ($byLast[$last] ?? [] as $v) {
                if ($firstOk(strtolower($v['first_name']))) {
                    return $v['id'];
                }
            }
            // last name spelled slightly differently (Wylie/Wiley, McGreevy/McGreevey)
            if (strlen($last) >= 4) {
                foreach ($byLast as $vl => $vs) {
                    if (levenshtein($vl, $last) <= (strlen($last) >= 5 ? 3 : 2)) {
                        foreach ($vs as $v) {
                            if ($firstOk(strtolower($v['first_name']))) {
                                return $v['id'];
                            }
                        }
                    }
                }
            }

            return null;
        };

        $csm = new CourseStaffModel();
        $esm = new EntryStaffModel();
        $created = $skipped = $staffN = 0;
        $unmatched = [];
        $newCourses = [];
        foreach ($data['entries'] ?? [] as $e) {
            $key = strtolower(trim($e['course'] ?? ''));
            if ($key === '') {
                continue;
            }
            if (! isset($courses[$key])) {
                $cm->insert(['name' => trim($e['course']), 'pillar' => $e['pillar'] ?? 'other', 'category' => $e['category'] ?? null,
                    'audience' => $e['audience'] ?? 'coed', 'active' => 1]);
                $courses[$key] = $cm->find($cm->getInsertID());
                $newCourses[]  = trim($e['course']) . ' [' . ($e['pillar'] ?? 'other') . ']';
            }
            $course = $courses[$key];
            $slotId = null;
            if (! empty($e['slot'])) {
                if (! isset($slotIds[$e['slot']])) {
                    $skipped++;
                    continue;
                }
                $slotId = $slotIds[$e['slot']];
            }
            $row = [
                'program_year_id' => $yid, 'course_id' => $course['id'], 'slot_id' => $slotId,
                'slot_span'  => max(1, (int) ($e['slot_span'] ?? 1)),
                'start_date' => $e['start_date'], 'end_date' => $e['end_date'] ?: $e['start_date'],
                'title'      => trim((string) ($e['title'] ?? '')) ?: null,
                'group_id'   => null, 'group_label' => $e['group'] ?: null,
                'audience'   => $e['audience'] ?? null,
                'location'   => $e['location'] ?: null, 'lo_la' => $e['lo_la'] ?: null,
                'status'     => in_array($e['status'] ?? '', ['planned', 'confirmed', 'done', 'missed', 'cancelled'], true) ? $e['status'] : 'planned',
                'comment'    => trim((string) ($e['comment'] ?? '')) ?: null,
            ];
            if ($row['group_label'] && isset($groupIds[strtoupper($row['group_label'])])) {
                $row['group_id'] = $groupIds[strtoupper($row['group_label'])];
            }
            $dup = $em->where('program_year_id', $yid)->where('course_id', $row['course_id'])->where('start_date', $row['start_date'])
                ->where('slot_id', $slotId)->where('title', $row['title'])->where('group_label', $row['group_label'])->first();
            if ($dup) {
                $skipped++;
                continue;
            }
            $em->insert($row);
            $eid = $em->getInsertID();
            $created++;
            foreach ($e['staff'] ?? [] as $s) {
                $vid = $findVol($s['name'] ?? '');
                if (! $vid) {
                    $unmatched[$s['name']] = ($unmatched[$s['name']] ?? 0) + 1;
                    continue;
                }
                $role = $s['role'] ?? 'instructor';
                $esm->insert(['entry_id' => $eid, 'volunteer_id' => $vid, 'role' => $role]);
                $staffN++;
                // remember the person as default staff on the course when it has none yet
                if (! $csm->where('course_id', $course['id'])->countAllResults() && $course['pillar'] !== 'event' && $course['pillar'] !== 'break') {
                    $csm->insert(['course_id' => $course['id'], 'volunteer_id' => $vid, 'role' => $role]);
                }
            }
        }
        CLI::write("Sessions: {$created} created, {$skipped} skipped (duplicates or unknown slot); {$staffN} staff links");
        if ($newCourses) {
            CLI::write('New courses: ' . implode(', ', $newCourses));
        }
        foreach ($unmatched as $n => $k) {
            CLI::write("  staff not on the volunteer list: {$n} ({$k})", 'red');
        }
        foreach ($data['warnings'] ?? [] as $w) {
            CLI::write('  parser: ' . $w, 'yellow');
        }
    }
}
