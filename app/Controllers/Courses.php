<?php

namespace App\Controllers;

use App\Models\CourseAssetModel;
use App\Models\CourseModel;
use App\Models\CourseStaffModel;
use App\Models\EntryStaffModel;
use App\Models\NoteModel;
use App\Models\ScheduleEntryModel;
use App\Models\ScheduleSlotModel;
use App\Models\VolunteerModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Schedule as Cfg;

/** Course catalog: what can be scheduled, who teaches it, its documents and notes. Courses are not year-scoped. */
class Courses extends BaseController
{
    public function index()
    {
        $cfg    = new Cfg();
        $pillar = (string) $this->request->getGet('pillar');
        $q      = trim((string) $this->request->getGet('q'));
        $b      = (new CourseModel())->orderBy('pillar')->orderBy('name');
        if (! $this->request->getGet('all')) {
            $b->where('active', 1);
        }
        if ($pillar !== '' && isset($cfg->pillars[$pillar])) {
            $b->where('pillar', $pillar);
        }
        if ($q !== '') {
            $b->like('name', $q);
        }
        $rows = $b->findAll();

        // staff names + session counts for the current year
        $staff = [];
        if ($rows) {
            foreach ((new CourseStaffModel())->select('course_staff.course_id, course_staff.role, volunteers.first_name, volunteers.last_name')
                ->join('volunteers', 'volunteers.id = course_staff.volunteer_id')->whereIn('course_staff.course_id', array_column($rows, 'id'))->findAll() as $s) {
                $staff[$s['course_id']][] = $s['first_name'] . ' ' . $s['last_name'];
            }
        }
        $counts = [];
        $year   = current_year();
        if ($year) {
            foreach ((new ScheduleEntryModel())->select('course_id, COUNT(*) AS n')->where('program_year_id', $year['id'])->groupBy('course_id')->findAll() as $c) {
                $counts[$c['course_id']] = (int) $c['n'];
            }
        }

        return view('courses/index', ['title' => 'Courses', 'rows' => $rows, 'staff' => $staff, 'counts' => $counts, 'cfg' => $cfg, 'pillar' => $pillar, 'q' => $q, 'year' => $year]);
    }

    public function create()
    {
        return view('courses/form', ['title' => 'New course', 'cfg' => new Cfg()]);
    }

    public function store()
    {
        $m = new CourseModel();
        $m->insert($this->data());

        return redirect()->to('/courses/' . $m->getInsertID())->with('success', 'Course created.');
    }

    public function show(int $id)
    {
        $course = $this->find($id);
        $staff  = (new CourseStaffModel())->select('course_staff.*, volunteers.first_name, volunteers.last_name, volunteers.email, volunteers.phone')
            ->join('volunteers', 'volunteers.id = course_staff.volunteer_id')->where('course_id', $id)->orderBy('role')->findAll();
        $assets = (new CourseAssetModel())->where('course_id', $id)->orderBy('id')->findAll();
        $year   = current_year();
        $entries = $year ? (new ScheduleEntryModel())->where('program_year_id', $year['id'])->where('course_id', $id)->orderBy('start_date')->orderBy('slot_id')->findAll() : [];
        $slots   = $year ? array_column((new ScheduleSlotModel())->where('program_year_id', $year['id'])->findAll(), null, 'id') : [];
        $hours   = 0.0;
        foreach ($entries as $e) {
            if ($e['status'] !== 'cancelled') {
                $hours += schedule_entry_hours($e, $slots);
            }
        }
        $entryStaff = [];
        if ($entries) {
            foreach ((new EntryStaffModel())->select('entry_staff.entry_id, entry_staff.role, volunteers.first_name, volunteers.last_name')
                ->join('volunteers', 'volunteers.id = entry_staff.volunteer_id')->whereIn('entry_id', array_column($entries, 'id'))->findAll() as $s) {
                $entryStaff[$s['entry_id']][] = $s['first_name'] . ' ' . $s['last_name'] . ($s['role'] !== 'instructor' ? ' (' . $s['role'] . ')' : '');
            }
        }
        $volunteers = (new VolunteerModel())->where('status !=', 'inactive')->orderBy('last_name')->orderBy('first_name')->findAll();
        $notes      = (new NoteModel())->forSubject('course', $id);

        return view('courses/show', ['title' => $course['name'], 'c' => $course, 'staff' => $staff, 'assets' => $assets, 'entries' => $entries, 'entryStaff' => $entryStaff,
            'slots' => $slots, 'hours' => $hours, 'volunteers' => $volunteers, 'notes' => $notes, 'cfg' => new Cfg(), 'year' => $year,
            'allCourses' => (new CourseModel())->orderBy('name')->findAll()]);
    }

    public function edit(int $id)
    {
        return view('courses/form', ['title' => 'Edit course', 'c' => $this->find($id), 'cfg' => new Cfg()]);
    }

    public function update(int $id)
    {
        $this->find($id);
        (new CourseModel())->update($id, $this->data());

        return redirect()->to('/courses/' . $id)->with('success', 'Course saved.');
    }

    public function delete(int $id)
    {
        $this->find($id);
        if ((new ScheduleEntryModel())->where('course_id', $id)->countAllResults()) {
            return redirect()->back()->with('error', 'This course has scheduled sessions; mark it inactive instead.');
        }
        (new CourseStaffModel())->where('course_id', $id)->delete();
        (new CourseAssetModel())->where('course_id', $id)->delete();
        (new NoteModel())->where('subject_type', 'course')->where('subject_id', $id)->delete();
        (new CourseModel())->delete($id);

        return redirect()->to('/courses')->with('success', 'Course removed.');
    }

    /** Fold this course into another: its sessions, recurrences, staff, assets and notes move over, then it is deleted. */
    public function merge(int $id)
    {
        $this->find($id);
        $into = (int) $this->request->getPost('into');
        if (! $into || $into === $id || ! (new CourseModel())->find($into)) {
            return redirect()->back()->with('error', 'Pick a course to merge into.');
        }
        (new ScheduleEntryModel())->where('course_id', $id)->set('course_id', $into)->update();
        (new \App\Models\ScheduleRecurrenceModel())->where('course_id', $id)->set('course_id', $into)->update();
        (new CourseAssetModel())->where('course_id', $id)->set('course_id', $into)->update();
        (new NoteModel())->where('subject_type', 'course')->where('subject_id', $id)->set('subject_id', $into)->update();
        $sm = new CourseStaffModel();
        foreach ($sm->where('course_id', $id)->findAll() as $s) {
            if ($sm->where('course_id', $into)->where('volunteer_id', $s['volunteer_id'])->where('role', $s['role'])->first()) {
                $sm->delete($s['id']);
            } else {
                $sm->update($s['id'], ['course_id' => $into]);
            }
        }
        (new CourseModel())->delete($id);

        return redirect()->to('/courses/' . $into)->with('success', 'Merged.');
    }

    public function addStaff(int $id)
    {
        $this->find($id);
        $vid  = (int) $this->request->getPost('volunteer_id');
        $role = (string) $this->request->getPost('role');
        if (! $vid || ! isset((new Cfg())->staffRoles[$role])) {
            return redirect()->back()->with('error', 'Pick a person and a role.');
        }
        $m = new CourseStaffModel();
        if (! $m->where('course_id', $id)->where('volunteer_id', $vid)->where('role', $role)->first()) {
            $m->insert(['course_id' => $id, 'volunteer_id' => $vid, 'role' => $role, 'notes' => trim((string) $this->request->getPost('notes')) ?: null]);
        }

        return redirect()->to('/courses/' . $id)->with('success', 'Staff added.');
    }

    public function removeStaff(int $id, int $sid)
    {
        (new CourseStaffModel())->where('course_id', $id)->delete($sid);

        return redirect()->to('/courses/' . $id)->with('success', 'Removed.');
    }

    /** An asset is a link or an uploaded file (stored under writable/uploads/courses, served through asset()). */
    public function addAsset(int $id)
    {
        $this->find($id);
        $title = trim((string) $this->request->getPost('title'));
        $url   = trim((string) $this->request->getPost('url')) ?: null;
        $file  = $this->request->getFile('file');
        $path  = null;
        if ($file && $file->isValid()) {
            $dir = WRITEPATH . 'uploads/courses/' . $id;
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $name = $file->getRandomName();
            $file->move($dir, $name);
            $path  = 'courses/' . $id . '/' . $name;
            $title = $title ?: $file->getClientName();
        }
        if ($title === '' || (! $url && ! $path)) {
            return redirect()->back()->with('error', 'Give the asset a title and a link or a file.');
        }
        (new CourseAssetModel())->insert(['course_id' => $id, 'title' => $title, 'url' => $url, 'file_path' => $path,
            'notes' => trim((string) $this->request->getPost('notes')) ?: null, 'created_at' => date('Y-m-d H:i:s')]);

        return redirect()->to('/courses/' . $id)->with('success', 'Asset added.');
    }

    public function removeAsset(int $id, int $aid)
    {
        $m = new CourseAssetModel();
        $a = $m->where('course_id', $id)->find($aid);
        if ($a) {
            if ($a['file_path'] && is_file(WRITEPATH . 'uploads/' . $a['file_path'])) {
                unlink(WRITEPATH . 'uploads/' . $a['file_path']);
            }
            $m->delete($aid);
        }

        return redirect()->to('/courses/' . $id)->with('success', 'Asset removed.');
    }

    public function asset(int $id, int $aid)
    {
        $a = (new CourseAssetModel())->where('course_id', $id)->find($aid);
        if (! $a || ! $a['file_path'] || ! is_file(WRITEPATH . 'uploads/' . $a['file_path'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download(WRITEPATH . 'uploads/' . $a['file_path'], null)->setFileName($a['title'] . '.' . pathinfo($a['file_path'], PATHINFO_EXTENSION));
    }

    private function find(int $id): array
    {
        $c = (new CourseModel())->find($id);
        if (! $c) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $c;
    }

    private function data(): array
    {
        $cfg = new Cfg();
        $p   = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;
        $pillar = (string) $p('pillar');
        $aud    = (string) $p('audience');

        return [
            'name'             => (string) $p('name'),
            'pillar'           => isset($cfg->pillars[$pillar]) ? $pillar : 'other',
            'category'         => in_array($p('category'), $cfg->categories, true) ? $p('category') : null,
            'audience'         => isset($cfg->audiences[$aud]) ? $aud : 'coed',
            'color'            => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $p('color')) ? $p('color') : null,
            'default_location' => $p('default_location'),
            'description'      => $p('description'),
            'moodle_course_id' => (int) $p('moodle_course_id') ?: null,
            'active'           => (int) (bool) $this->request->getPost('active'),
        ];
    }
}
