<?php

namespace App\Controllers;

use App\Models\CourseModel;
use App\Models\CourseStaffModel;
use App\Models\EntryStaffModel;
use App\Models\GroupModel;
use App\Models\GroupSetModel;
use App\Models\ScheduleBlockModel;
use App\Models\ScheduleEntryModel;
use App\Models\ScheduleRecurrenceModel;
use App\Models\ScheduleSlotModel;
use App\Models\VolunteerModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Schedule as Cfg;

/**
 * The weekly / yearly class map for the selected program year: time slots, blocks,
 * one-off and recurring entries, per-session staff, and the roll-up views.
 */
class Schedule extends BaseController
{
    // ---------------------------------------------------------------- views

    public function week()
    {
        $year = $this->currentYear();
        $date = $this->request->getGet('date') ?: date('Y-m-d');
        if (! strtotime($date)) {
            $date = date('Y-m-d');
        }
        $mon  = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $sun  = date('Y-m-d', strtotime($mon . ' +6 days'));
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = date('Y-m-d', strtotime($mon . " +{$i} days"));
        }
        [$slots, $grid, $allDay, $courses, $staff] = $this->loadGrid($year['id'], $mon, $sun);
        $blocks = (new ScheduleBlockModel())->where('program_year_id', $year['id'])->where('start_date <=', $sun)->where('end_date >=', $mon)->findAll();

        return view('schedule/week', ['title' => 'Schedule', 'year' => $year, 'days' => $days, 'mon' => $mon, 'slots' => $slots, 'grid' => $grid, 'allDay' => $allDay,
            'courses' => $courses, 'staff' => $staff, 'blocks' => $blocks, 'cfg' => new Cfg(), 'week' => gap_week($mon, $year)]);
    }

    public function year()
    {
        $year  = $this->currentYear();
        $from  = $year['start_date'] ?: $year['label'] . '-08-01';
        $to    = $year['end_date'] ?: (substr($year['label'], 0, 2) . substr($year['label'], 5, 2) . '-05-31');
        [$slots, $grid, $allDay, $courses, $staff] = $this->loadGrid($year['id'], $from, $to);
        // trim to the first/last day that has anything, with a week of margin
        $dates = array_keys(array_filter($grid, fn ($d) => array_filter($d)) + array_filter($allDay));
        if ($dates) {
            sort($dates);
            $from = max($from, date('Y-m-d', strtotime(min($dates) . ' -3 days')));
            $to   = min($to, date('Y-m-d', strtotime(max($dates) . ' +3 days')));
        }
        $days = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
            $days[] = date('Y-m-d', $d);
        }
        $blocks = (new ScheduleBlockModel())->where('program_year_id', $year['id'])->orderBy('start_date')->findAll();

        return view('schedule/year', ['title' => 'Year map ' . $year['label'], 'year' => $year, 'days' => $days, 'slots' => $slots, 'grid' => $grid, 'allDay' => $allDay,
            'courses' => $courses, 'staff' => $staff, 'blocks' => $blocks, 'cfg' => new Cfg()]);
    }

    public function grid()
    {
        $year    = $this->currentYear();
        $entries = (new ScheduleEntryModel())->where('program_year_id', $year['id'])->where('status !=', 'cancelled')->findAll();
        $courses = array_column((new CourseModel())->findAll(), null, 'id');
        $weeks   = [];
        $matrix  = [];   // course_id => week => count
        foreach ($entries as $e) {
            for ($d = strtotime($e['start_date']); $d <= strtotime($e['end_date']); $d += 86400) {
                $w = gap_week(date('Y-m-d', $d), $year) ?? 0;
                $weeks[$w] = true;
                $matrix[$e['course_id']][$w] = ($matrix[$e['course_id']][$w] ?? 0) + 1;
            }
        }
        ksort($weeks);
        $rows = [];
        foreach ($matrix as $cid => $byWeek) {
            if (isset($courses[$cid])) {
                $rows[] = ['course' => $courses[$cid], 'weeks' => $byWeek, 'total' => array_sum($byWeek)];
            }
        }
        $cfg   = new Cfg();
        $order = array_flip(array_keys($cfg->pillars));
        usort($rows, fn ($a, $b) => ($order[$a['course']['pillar']] ?? 99) <=> ($order[$b['course']['pillar']] ?? 99) ?: strcmp($a['course']['name'], $b['course']['name']));
        $weekStarts = [];
        foreach (array_keys($weeks) as $w) {
            $weekStarts[$w] = $w && $year['week_one_starts'] ? date('M j', strtotime($year['week_one_starts'] . ' +' . (($w - 1) * 7) . ' days')) : '';
        }

        return view('schedule/grid', ['title' => 'Year grid ' . $year['label'], 'year' => $year, 'weeks' => array_keys($weeks), 'weekStarts' => $weekStarts, 'rows' => $rows, 'cfg' => $cfg]);
    }

    public function hours()
    {
        $year    = $this->currentYear();
        $slots   = array_column((new ScheduleSlotModel())->where('program_year_id', $year['id'])->findAll(), null, 'id');
        $courses = array_column((new CourseModel())->findAll(), null, 'id');
        $entries = (new ScheduleEntryModel())->where('program_year_id', $year['id'])->where('status !=', 'cancelled')->findAll();
        $byCat   = [];   // category => ['total' => h, 'groups' => [label => h], 'courses' => [name => h]]
        foreach ($entries as $e) {
            $c = $courses[$e['course_id']] ?? null;
            if (! $c) {
                continue;
            }
            $h = schedule_entry_hours($e, $slots);
            if ($h <= 0) {
                continue;
            }
            $cat = $c['category'] ?: ((new Cfg())->pillars[$c['pillar']][0] ?? 'Other');
            $aud = $e['audience'] ?: ($e['slot_id'] && isset($slots[$e['slot_id']]) ? $slots[$e['slot_id']]['audience'] : $c['audience']);
            $g   = trim(($aud !== 'coed' ? ucfirst($aud) : 'Coed') . ($e['group_label'] ? ' ' . $e['group_label'] : ''));
            $byCat[$cat]['total']            = ($byCat[$cat]['total'] ?? 0) + $h;
            $byCat[$cat]['groups'][$g]       = ($byCat[$cat]['groups'][$g] ?? 0) + $h;
            $byCat[$cat]['courses'][$c['name']] = ($byCat[$cat]['courses'][$c['name']] ?? 0) + $h;
        }
        uasort($byCat, fn ($a, $b) => $b['total'] <=> $a['total']);

        return view('schedule/hours', ['title' => 'Hours ' . $year['label'], 'year' => $year, 'byCat' => $byCat, 'cfg' => new Cfg()]);
    }

    public function staff()
    {
        $year = $this->currentYear();
        $from = $this->request->getGet('from') ?: date('Y-m-d');
        $all  = $this->request->getGet('all');
        $b    = (new EntryStaffModel())->select('entry_staff.role AS staff_role, entry_staff.volunteer_id, schedule_entries.*, volunteers.first_name, volunteers.last_name, volunteers.email, volunteers.phone, courses.name AS course_name')
            ->join('schedule_entries', 'schedule_entries.id = entry_staff.entry_id')
            ->join('volunteers', 'volunteers.id = entry_staff.volunteer_id')
            ->join('courses', 'courses.id = schedule_entries.course_id')
            ->where('schedule_entries.program_year_id', $year['id'])->orderBy('volunteers.last_name')->orderBy('volunteers.first_name')->orderBy('schedule_entries.start_date');
        if (! $all) {
            $b->where('schedule_entries.end_date >=', $from);
        }
        $rows  = $b->findAll();
        $slots = array_column((new ScheduleSlotModel())->where('program_year_id', $year['id'])->findAll(), null, 'id');
        $byPerson = [];
        foreach ($rows as $r) {
            $byPerson[$r['volunteer_id']]['person'] = $r;
            $byPerson[$r['volunteer_id']]['sessions'][] = $r;
        }

        return view('schedule/staff', ['title' => 'Who teaches when', 'year' => $year, 'byPerson' => $byPerson, 'slots' => $slots, 'from' => $from, 'all' => (bool) $all, 'cfg' => new Cfg()]);
    }

    // ---------------------------------------------------------------- entries

    public function create()
    {
        $year = $this->currentYear();
        $pre  = ['start_date' => $this->request->getGet('date') ?: date('Y-m-d'), 'slot_id' => (int) $this->request->getGet('slot') ?: null, 'course_id' => (int) $this->request->getGet('course') ?: null];
        $pre['end_date'] = $pre['start_date'];

        return view('schedule/entry_form', ['title' => 'New session', 'e' => $pre, 'year' => $year] + $this->formData($year));
    }

    public function store()
    {
        $year = $this->currentYear();
        $data = $this->entryData($year);
        if ($data instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return $data;
        }
        $m = new ScheduleEntryModel();
        $m->insert($data);
        $id = $m->getInsertID();
        $this->copyCourseStaff($data['course_id'], $id);

        return redirect()->to('/schedule/entries/' . $id)->with('success', 'Session added.');
    }

    public function edit(int $id)
    {
        $year = $this->currentYear();
        $e    = $this->entry($id, $year);
        $staff = (new EntryStaffModel())->select('entry_staff.*, volunteers.first_name, volunteers.last_name, volunteers.email, volunteers.phone')
            ->join('volunteers', 'volunteers.id = entry_staff.volunteer_id')->where('entry_id', $id)->findAll();

        return view('schedule/entry_form', ['title' => 'Session', 'e' => $e, 'year' => $year, 'entryStaff' => $staff] + $this->formData($year));
    }

    public function update(int $id)
    {
        $year = $this->currentYear();
        $this->entry($id, $year);
        $data = $this->entryData($year);
        if ($data instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return $data;
        }
        (new ScheduleEntryModel())->update($id, $data);
        $back = $this->request->getPost('return') ?: '/schedule/entries/' . $id;

        return redirect()->to($back)->with('success', 'Session saved.');
    }

    public function delete(int $id)
    {
        $year = $this->currentYear();
        $e    = $this->entry($id, $year);
        (new EntryStaffModel())->where('entry_id', $id)->delete();
        (new ScheduleEntryModel())->delete($id);

        return redirect()->to('/schedule?date=' . $e['start_date'])->with('success', 'Session removed.');
    }

    public function addStaff(int $id)
    {
        $this->entry($id, $this->currentYear());
        $vid  = (int) $this->request->getPost('volunteer_id');
        $role = (string) $this->request->getPost('role');
        if ($vid && isset((new Cfg())->staffRoles[$role])) {
            $m = new EntryStaffModel();
            if (! $m->where('entry_id', $id)->where('volunteer_id', $vid)->where('role', $role)->first()) {
                $m->insert(['entry_id' => $id, 'volunteer_id' => $vid, 'role' => $role]);
            }
        }

        return redirect()->to('/schedule/entries/' . $id)->with('success', 'Added.');
    }

    public function removeStaff(int $id, int $sid)
    {
        (new EntryStaffModel())->where('entry_id', $id)->delete($sid);

        return redirect()->to('/schedule/entries/' . $id)->with('success', 'Removed.');
    }

    // ---------------------------------------------------------------- recurring

    public function recurring()
    {
        $year  = $this->currentYear();
        $rows  = (new ScheduleRecurrenceModel())->where('program_year_id', $year['id'])->orderBy('weekday')->orderBy('slot_id')->findAll();
        $counts = [];
        if ($rows) {
            foreach ((new ScheduleEntryModel())->select('recurrence_id, COUNT(*) AS n')->whereIn('recurrence_id', array_column($rows, 'id'))->groupBy('recurrence_id')->findAll() as $c) {
                $counts[$c['recurrence_id']] = (int) $c['n'];
            }
        }

        return view('schedule/recurring', ['title' => 'Standing week', 'year' => $year, 'rows' => $rows, 'counts' => $counts] + $this->formData($year));
    }

    public function saveRecurrence()
    {
        $year = $this->currentYear();
        $id   = (int) $this->request->getPost('id');
        $p    = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;
        $cfg  = new Cfg();
        $data = [
            'program_year_id' => $year['id'],
            'course_id'  => (int) $p('course_id'),
            'slot_id'    => (int) $p('slot_id') ?: null,
            'slot_span'  => max(1, (int) $p('slot_span')),
            'weekday'    => (int) $p('weekday'),
            'start_date' => $p('start_date') ?: $year['start_date'],
            'end_date'   => $p('end_date') ?: $year['end_date'],
            'title'      => $p('title'),
            'group_id'   => (int) $p('group_id') ?: null,
            'audience'   => isset($cfg->audiences[(string) $p('audience')]) ? $p('audience') : null,
            'location'   => $p('location'),
            'lo_la'      => $p('lo_la'),
            'notes'      => $p('notes'),
        ];
        if (! $data['course_id'] || $data['weekday'] < 1 || $data['weekday'] > 7 || ! $data['start_date'] || ! $data['end_date']) {
            return redirect()->back()->with('error', 'Course, weekday and a date range are required.');
        }
        $m = new ScheduleRecurrenceModel();
        if ($id) {
            $m->where('program_year_id', $year['id'])->update($id, $data);
        } else {
            $m->insert($data);
            $id = $m->getInsertID();
        }
        $n = $this->materialise($m->find($id), $year);

        return redirect()->to('/schedule/recurring')->with('success', "Saved; {$n} session(s) added to the calendar.");
    }

    public function deleteRecurrence(int $id)
    {
        $year = $this->currentYear();
        $r    = (new ScheduleRecurrenceModel())->where('program_year_id', $year['id'])->find($id);
        if (! $r) {
            throw PageNotFoundException::forPageNotFound();
        }
        $ids = array_column((new ScheduleEntryModel())->where('recurrence_id', $id)->findAll(), 'id');
        if ($ids) {
            (new EntryStaffModel())->whereIn('entry_id', $ids)->delete();
            (new ScheduleEntryModel())->whereIn('id', $ids)->delete();
        }
        (new ScheduleRecurrenceModel())->delete($id);

        return redirect()->to('/schedule/recurring')->with('success', 'Pattern and its ' . count($ids) . ' generated session(s) removed.');
    }

    /** Re-run the generator: adds sessions for dates that have none (never touches existing ones). */
    public function generate(int $id)
    {
        $year = $this->currentYear();
        $r    = (new ScheduleRecurrenceModel())->where('program_year_id', $year['id'])->find($id);
        if (! $r) {
            throw PageNotFoundException::forPageNotFound();
        }
        $n = $this->materialise($r, $year);

        return redirect()->to('/schedule/recurring')->with('success', "{$n} session(s) added.");
    }

    // ---------------------------------------------------------------- settings (slots + blocks)

    public function settings()
    {
        $year   = $this->currentYear();
        $slots  = (new ScheduleSlotModel())->where('program_year_id', $year['id'])->orderBy('sort_order')->orderBy('id')->findAll();
        $blocks = (new ScheduleBlockModel())->where('program_year_id', $year['id'])->orderBy('start_date')->findAll();
        $prev   = null;
        foreach (all_years() as $y) {
            if ($y['year_number'] == $year['year_number'] - 1) {
                $prev = $y;
            }
        }

        return view('schedule/settings', ['title' => 'Schedule settings', 'year' => $year, 'slots' => $slots, 'blocks' => $blocks, 'prev' => $prev, 'cfg' => new Cfg()]);
    }

    public function saveSlot()
    {
        $year = $this->currentYear();
        $p    = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;
        $t    = fn (?string $v) => $v && preg_match('/^\d{1,2}:\d{2}/', $v) ? substr($v, 0, 5) : null;
        $data = ['program_year_id' => $year['id'], 'label' => (string) $p('label'), 'audience' => isset((new Cfg())->audiences[(string) $p('audience')]) ? $p('audience') : 'coed',
            'start_time' => $t($p('start_time')), 'end_time' => $t($p('end_time')), 'sort_order' => (int) $p('sort_order')];
        if ($data['label'] === '') {
            return redirect()->back()->with('error', 'Give the slot a label.');
        }
        $m  = new ScheduleSlotModel();
        $id = (int) $p('id');
        $id ? $m->where('program_year_id', $year['id'])->update($id, $data) : $m->insert($data);

        return redirect()->to('/schedule/settings')->with('success', 'Slot saved.');
    }

    public function deleteSlot(int $id)
    {
        $year = $this->currentYear();
        if ((new ScheduleEntryModel())->where('slot_id', $id)->countAllResults()) {
            return redirect()->back()->with('error', 'That slot has sessions in it; move or delete them first.');
        }
        (new ScheduleSlotModel())->where('program_year_id', $year['id'])->delete($id);

        return redirect()->to('/schedule/settings')->with('success', 'Slot removed.');
    }

    public function saveBlock()
    {
        $year = $this->currentYear();
        $p    = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;
        $data = ['program_year_id' => $year['id'], 'label' => (string) $p('label'), 'start_date' => $p('start_date'), 'end_date' => $p('end_date') ?: $p('start_date'),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $p('color')) ? $p('color') : null];
        if ($data['label'] === '' || ! $data['start_date']) {
            return redirect()->back()->with('error', 'A block needs a label and a start date.');
        }
        $m  = new ScheduleBlockModel();
        $id = (int) $p('id');
        $id ? $m->where('program_year_id', $year['id'])->update($id, $data) : $m->insert($data);

        return redirect()->to('/schedule/settings')->with('success', 'Block saved.');
    }

    public function deleteBlock(int $id)
    {
        (new ScheduleBlockModel())->where('program_year_id', $this->currentYear()['id'])->delete($id);

        return redirect()->to('/schedule/settings')->with('success', 'Block removed.');
    }

    /** Copy the previous year's slots (and blocks shifted by one year) into this year when it has none. */
    public function copySettings()
    {
        $year = $this->currentYear();
        $from = (int) $this->request->getPost('from');
        $sm   = new ScheduleSlotModel();
        $bm   = new ScheduleBlockModel();
        if ($sm->where('program_year_id', $year['id'])->countAllResults()) {
            return redirect()->back()->with('error', 'This year already has slots.');
        }
        foreach ($sm->where('program_year_id', $from)->findAll() as $s) {
            unset($s['id']);
            $sm->insert(['program_year_id' => $year['id']] + $s);
        }
        if (! $bm->where('program_year_id', $year['id'])->countAllResults()) {
            foreach ($bm->where('program_year_id', $from)->findAll() as $b) {
                unset($b['id']);
                $b['start_date'] = date('Y-m-d', strtotime($b['start_date'] . ' +1 year'));
                $b['end_date']   = date('Y-m-d', strtotime($b['end_date'] . ' +1 year'));
                $bm->insert(['program_year_id' => $year['id']] + $b);
            }
        }

        return redirect()->to('/schedule/settings')->with('success', 'Copied. Check the block dates: they were shifted by one year, not to the matching weekdays.');
    }

    // ---------------------------------------------------------------- internals

    private function currentYear(): array
    {
        $y = current_year();
        if (! $y) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $y;
    }

    private function entry(int $id, array $year): array
    {
        $e = (new ScheduleEntryModel())->where('program_year_id', $year['id'])->find($id);
        if (! $e) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $e;
    }

    /** Slots, courses, groups and volunteers for the entry / recurrence forms. */
    private function formData(array $year): array
    {
        $groups = [];
        foreach ((new GroupSetModel())->where('program_year_id', $year['id'])->orderBy('sort_order')->findAll() as $set) {
            foreach ((new GroupModel())->where('group_set_id', $set['id'])->orderBy('sort_order')->orderBy('id')->findAll() as $g) {
                $groups[$g['id']] = $set['name'] . ': ' . $set['group_label'] . ' ' . $g['name'];
            }
        }

        return [
            'slots'      => (new ScheduleSlotModel())->where('program_year_id', $year['id'])->orderBy('sort_order')->orderBy('id')->findAll(),
            'courseList' => (new CourseModel())->where('active', 1)->orderBy('pillar')->orderBy('name')->findAll(),
            'groups'     => $groups,
            'volunteers' => (new VolunteerModel())->where('status !=', 'inactive')->orderBy('last_name')->orderBy('first_name')->findAll(),
            'cfg'        => new Cfg(),
        ];
    }

    /** Validated entry fields from the POST, or a redirect with the error. */
    private function entryData(array $year)
    {
        $cfg = new Cfg();
        $p   = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;
        $groups = $this->formData($year)['groups'];
        $gid    = (int) $p('group_id') ?: null;
        $data   = [
            'program_year_id' => $year['id'],
            'course_id'   => (int) $p('course_id'),
            'slot_id'     => $this->request->getPost('all_day') ? null : ((int) $p('slot_id') ?: null),
            'slot_span'   => max(1, (int) $p('slot_span')),
            'start_date'  => $p('start_date'),
            'end_date'    => $p('end_date') ?: $p('start_date'),
            'title'       => $p('title'),
            'group_id'    => $gid && isset($groups[$gid]) ? $gid : null,
            'group_label' => $p('group_label') ?: ($gid && isset($groups[$gid]) ? preg_replace('/^.*: /', '', $groups[$gid]) : null),
            'audience'    => isset($cfg->audiences[(string) $p('audience')]) ? $p('audience') : null,
            'location'    => $p('location'),
            'lo_la'       => $p('lo_la'),
            'status'      => isset($cfg->statuses[(string) $p('status')]) ? $p('status') : 'planned',
            'comment'     => $p('comment'),
        ];
        if (! $data['course_id'] || ! $data['start_date'] || ! strtotime($data['start_date'])) {
            return redirect()->back()->withInput()->with('error', 'Pick a course and a date.');
        }
        if ($data['end_date'] < $data['start_date']) {
            $data['end_date'] = $data['start_date'];
        }

        return $data;
    }

    private function copyCourseStaff(int $courseId, int $entryId): void
    {
        $em = new EntryStaffModel();
        foreach ((new CourseStaffModel())->where('course_id', $courseId)->findAll() as $s) {
            $em->insert(['entry_id' => $entryId, 'volunteer_id' => $s['volunteer_id'], 'role' => $s['role']]);
        }
    }

    /** Create entries for a recurrence on every matching weekday in its range that has none yet; skips days inside a break. */
    private function materialise(array $r, array $year): int
    {
        $em     = new ScheduleEntryModel();
        $have   = [];
        foreach ($em->where('recurrence_id', $r['id'])->findAll() as $e) {
            $have[$e['start_date']] = true;
        }
        $breaks = $em->select('schedule_entries.start_date, schedule_entries.end_date')->join('courses', 'courses.id = schedule_entries.course_id')
            ->where('schedule_entries.program_year_id', $year['id'])->where('schedule_entries.slot_id', null)->where('courses.pillar', 'break')->findAll();
        $n = 0;
        for ($d = strtotime($r['start_date']); $d <= strtotime($r['end_date']); $d += 86400) {
            if ((int) date('N', $d) !== (int) $r['weekday']) {
                continue;
            }
            $date = date('Y-m-d', $d);
            if (isset($have[$date])) {
                continue;
            }
            foreach ($breaks as $b) {
                if ($date >= $b['start_date'] && $date <= $b['end_date']) {
                    continue 2;
                }
            }
            $em->insert(['program_year_id' => $year['id'], 'course_id' => $r['course_id'], 'slot_id' => $r['slot_id'], 'slot_span' => $r['slot_span'],
                'start_date' => $date, 'end_date' => $date, 'title' => $r['title'], 'group_id' => $r['group_id'], 'audience' => $r['audience'],
                'location' => $r['location'], 'lo_la' => $r['lo_la'], 'status' => 'planned', 'recurrence_id' => $r['id']]);
            $this->copyCourseStaff($r['course_id'], $em->getInsertID());
            $n++;
        }

        return $n;
    }

    /**
     * Everything needed to draw a date range: [slots (ordered), grid[date][slot_id] => entries, allDay[date] => entries, courses by id, staff[entry_id] => names].
     * Entries that span several days appear under each day.
     */
    private function loadGrid(int $yearId, string $from, string $to): array
    {
        $slots   = (new ScheduleSlotModel())->where('program_year_id', $yearId)->orderBy('sort_order')->orderBy('id')->findAll();
        $entries = (new ScheduleEntryModel())->where('program_year_id', $yearId)->where('start_date <=', $to)->where('end_date >=', $from)->orderBy('audience')->orderBy('group_label')->orderBy('id')->findAll();
        $courses = array_column((new CourseModel())->findAll(), null, 'id');
        $grid = $allDay = $staff = [];
        if ($entries) {
            foreach ((new EntryStaffModel())->select('entry_staff.entry_id, entry_staff.role, volunteers.first_name, volunteers.last_name')
                ->join('volunteers', 'volunteers.id = entry_staff.volunteer_id')->whereIn('entry_id', array_column($entries, 'id'))->findAll() as $s) {
                $staff[$s['entry_id']][] = $s['first_name'] . ' ' . $s['last_name'] . ($s['role'] !== 'instructor' ? ' (' . $s['role'] . ')' : '');
            }
        }
        foreach ($entries as $e) {
            for ($d = max(strtotime($e['start_date']), strtotime($from)); $d <= min(strtotime($e['end_date']), strtotime($to)); $d += 86400) {
                $date = date('Y-m-d', $d);
                if ($e['slot_id']) {
                    $grid[$date][$e['slot_id']][] = $e;
                } else {
                    $allDay[$date][] = $e;
                }
            }
        }

        return [$slots, $grid, $allDay, $courses, $staff];
    }
}
