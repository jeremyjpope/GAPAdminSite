<?php

namespace App\Controllers;

use App\Models\HostHomeModel;
use App\Models\HostHomePersonModel;
use App\Models\NoteModel;
use App\Models\StudentModel;
use App\Models\VolunteerModel;
use App\Models\VolunteerRoleModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Volunteers extends BaseController
{
    private VolunteerModel $vols;

    public function __construct()
    {
        $this->vols = new VolunteerModel();
    }

    public function index()
    {
        $cfg    = config('Volunteers');
        $q      = trim((string) $this->request->getGet('q'));
        $role   = (string) $this->request->getGet('role');
        $status = (string) $this->request->getGet('status');

        $b = $this->vols;
        if ($q !== '') {
            $b = $b->groupStart()->like('first_name', $q)->orLike('last_name', $q)->orLike('email', $q)->orLike('notes', $q)->groupEnd();
        }
        if ($status !== '' && isset($cfg->statuses[$status])) {
            $b = $b->where('status', $status);
        }
        if ($role !== '') {
            $ids = array_column((new VolunteerRoleModel())->where('role', $role)->findAll(), 'volunteer_id') ?: [0];
            $b   = $b->whereIn('id', $ids);
        }
        $rows  = $b->orderBy('last_name')->orderBy('first_name')->findAll();
        $roles = $this->vols->rolesFor(array_column($rows, 'id'));

        // role counts for the filter chips
        $counts = [];
        foreach ((new VolunteerRoleModel())->select('role, COUNT(*) AS n')->groupBy('role')->findAll() as $r) {
            $counts[$r['role']] = (int) $r['n'];
        }

        return view('volunteers/index', ['title' => 'Volunteers', 'rows' => $rows, 'roles' => $roles, 'q' => $q, 'role' => $role, 'status' => $status, 'cfg' => $cfg, 'counts' => $counts]);
    }

    public function show(int $id)
    {
        $v     = $this->mustFind($id);
        $roles = $this->vols->rolesFor([$id])[$id] ?? [];
        $homes = [];
        foreach ((new HostHomePersonModel())->where('volunteer_id', $id)->findAll() as $l) {
            if ($h = (new HostHomeModel())->find($l['host_home_id'])) {
                $homes[] = $h;
            }
        }
        $mentees = (new StudentModel())->where('mentor_id', $id)->orderBy('last_name')->findAll();
        $year     = current_year();
        $teaching = $year ? (new \App\Models\EntryStaffModel())->select('entry_staff.role AS staff_role, schedule_entries.id, schedule_entries.start_date, schedule_entries.end_date, schedule_entries.title, schedule_entries.group_label, schedule_entries.status, courses.name AS course_name')
            ->join('schedule_entries', 'schedule_entries.id = entry_staff.entry_id')->join('courses', 'courses.id = schedule_entries.course_id')
            ->where('entry_staff.volunteer_id', $id)->where('schedule_entries.program_year_id', $year['id'])->orderBy('schedule_entries.start_date')->findAll() : [];
        $courseStaff = (new \App\Models\CourseStaffModel())->select('course_staff.role, courses.id, courses.name')->join('courses', 'courses.id = course_staff.course_id')->where('volunteer_id', $id)->findAll();

        return view('volunteers/show', [
            'title' => trim($v['first_name'] . ' ' . $v['last_name']), 'v' => $v, 'roles' => $roles, 'homes' => $homes, 'mentees' => $mentees,
            'cfg' => config('Volunteers'), 'notes' => (new NoteModel())->forSubject('volunteer', $id), 'teaching' => $teaching, 'courseStaff' => $courseStaff,
        ]);
    }

    public function create()
    {
        return view('volunteers/form', ['title' => 'New volunteer', 'v' => null, 'cfg' => config('Volunteers'), 'preRole' => (string) $this->request->getGet('role')]);
    }

    public function store()
    {
        $data = $this->collect();
        if ($data['first_name'] === '' || $data['last_name'] === '') {
            return redirect()->back()->withInput()->with('error', 'First and last name are required.');
        }
        $this->vols->insert($data);
        $id = $this->vols->getInsertID();
        $this->applyRoles($id);

        return redirect()->to('/volunteers/' . $id)->with('success', 'Volunteer added.');
    }

    public function edit(int $id)
    {
        $v = $this->mustFind($id);

        return view('volunteers/form', ['title' => 'Edit ' . $v['first_name'] . ' ' . $v['last_name'], 'v' => $v, 'cfg' => config('Volunteers'), 'preRole' => '']);
    }

    public function update(int $id)
    {
        $this->mustFind($id);
        $data = $this->collect();
        if ($data['first_name'] === '' || $data['last_name'] === '') {
            return redirect()->back()->withInput()->with('error', 'First and last name are required.');
        }
        $this->vols->update($id, $data);

        return redirect()->to('/volunteers/' . $id)->with('success', 'Saved.');
    }

    /** Replace the volunteer's role tags from the roles form on the profile page. */
    public function saveRoles(int $id)
    {
        $this->mustFind($id);
        $this->applyRoles($id, true);

        return redirect()->to('/volunteers/' . $id)->with('success', 'Roles saved.');
    }

    public function delete(int $id)
    {
        $v = $this->mustFind($id);
        if ((new StudentModel())->where('mentor_id', $id)->countAllResults()) {
            return redirect()->back()->with('error', 'This volunteer is assigned as a mentor; reassign the student first.');
        }
        (new HostHomePersonModel())->where('volunteer_id', $id)->delete();
        (new VolunteerRoleModel())->where('volunteer_id', $id)->delete();
        (new NoteModel())->where('subject_type', 'volunteer')->where('subject_id', $id)->delete();
        $this->vols->delete($id);

        return redirect()->to('/volunteers')->with('success', 'Volunteer removed.');
    }

    // ---- helpers ----

    private function mustFind(int $id): array
    {
        $v = $this->vols->find($id);
        if (! $v) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $v;
    }

    private function collect(): array
    {
        $p = fn (string $k) => ($v = trim((string) $this->request->getPost($k))) === '' ? null : $v;
        $cfg = config('Volunteers');

        return [
            'first_name' => (string) $p('first_name'), 'last_name' => (string) $p('last_name'),
            'email' => $p('email'), 'phone' => $p('phone'), 'address' => $p('address'), 'city_state_zip' => $p('city_state_zip'),
            'gender' => $p('gender'), 'status' => isset($cfg->statuses[(string) $p('status')]) ? $p('status') : 'active',
            'member_of_dbc' => $p('member_of_dbc'), 'tshirt_size' => $p('tshirt_size'),
            'mp_group_placed_on' => $p('mp_group_placed_on'), 'opportunity_response' => $p('opportunity_response'),
            'driver_cleared_year' => $p('driver_cleared_year') ? (int) $p('driver_cleared_year') : null,
            'background_check' => $p('background_check'), 'saat_training' => $p('saat_training'), 'notes' => $p('notes'),
        ];
    }

    /** Roles come as roles[] (names), category[role] and lead[role]. */
    private function applyRoles(int $id, bool $replace = false): void
    {
        $rm  = new VolunteerRoleModel();
        $cfg = config('Volunteers');
        $sel = array_values(array_intersect((array) $this->request->getPost('roles'), $cfg->roles));
        $cats = (array) $this->request->getPost('category');
        $leads = (array) $this->request->getPost('lead');
        if ($replace) {
            $rm->where('volunteer_id', $id)->whereNotIn('role', $sel ?: [''])->delete();
        }
        foreach ($sel as $role) {
            $row = $rm->where('volunteer_id', $id)->where('role', $role)->first();
            $data = ['volunteer_id' => $id, 'role' => $role, 'category' => trim((string) ($cats[$role] ?? '')) ?: null, 'is_lead' => isset($leads[$role]) ? 1 : 0];
            $row ? $rm->update($row['id'], $data) : $rm->insert($data);
        }
    }
}
