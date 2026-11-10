<?php

namespace App\Controllers;

use App\Models\HostHomeModel;
use App\Models\HostHomePersonModel;
use App\Models\NoteModel;
use App\Models\StudentModel;
use App\Models\VolunteerModel;
use App\Models\VolunteerRoleModel;

/**
 * Host homes are households for a program year. The people in a home are volunteers
 * (linked through host_home_people); their contact details live on the volunteer record.
 */
class HostHomes extends BaseController
{
    public function index()
    {
        $year     = current_year();
        $homes    = (new HostHomeModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('family_name')->findAll();
        $students = (new StudentModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('last_name')->findAll();
        $byHome   = [];
        foreach ($students as $s) {
            $byHome[$s['host_home_id'] ?: 0][] = $s;
        }
        $people     = (new HostHomePersonModel())->peopleFor(array_column($homes, 'id'));
        $noteCounts = (new NoteModel())->countsFor('host_home', array_column($homes, 'id'));
        $volunteers = (new VolunteerModel())->where('status !=', 'inactive')->orderBy('last_name')->orderBy('first_name')->findAll();

        return view('housing/index', ['title' => 'Host Homes', 'year' => $year, 'homes' => $homes, 'students' => $students, 'byHome' => $byHome,
            'people' => $people, 'noteCounts' => $noteCounts, 'volunteers' => $volunteers]);
    }

    public function store()
    {
        $data = $this->collect();
        $data['program_year_id'] = current_year()['id'];
        $m = new HostHomeModel();
        $m->insert($data);

        return redirect()->to('/housing')->with('success', 'Host home added. Now add the people who live there.');
    }

    public function update(int $id)
    {
        (new HostHomeModel())->update($id, $this->collect());

        return redirect()->to('/housing')->with('success', 'Host home saved.');
    }

    public function delete(int $id)
    {
        (new StudentModel())->where('host_home_id', $id)->set(['host_home_id' => null])->update();
        (new HostHomePersonModel())->where('host_home_id', $id)->delete();
        (new NoteModel())->where('subject_type', 'host_home')->where('subject_id', $id)->delete();
        (new HostHomeModel())->delete($id);

        return redirect()->to('/housing')->with('success', 'Host home removed (the people stay in Volunteers).');
    }

    /** Link an existing volunteer to a home and give them the Host Family role. */
    public function addPerson(int $id)
    {
        $vid = (int) $this->request->getPost('volunteer_id');
        $pm  = new HostHomePersonModel();
        if ($vid && ! $pm->where('host_home_id', $id)->where('volunteer_id', $vid)->first()) {
            $pm->insert(['host_home_id' => $id, 'volunteer_id' => $vid]);
            (new VolunteerRoleModel())->ensure($vid, 'Host Family');
        }

        return redirect()->to('/housing')->with('success', 'Person added to the home.');
    }

    public function removePerson(int $id, int $vid)
    {
        (new HostHomePersonModel())->where('host_home_id', $id)->where('volunteer_id', $vid)->delete();

        return redirect()->to('/housing')->with('success', 'Person removed from the home (still a volunteer).');
    }

    /** Assign a student to a home (or unassign with home_id = 0). */
    public function assign()
    {
        $sid = (int) $this->request->getPost('student_id');
        $hid = (int) $this->request->getPost('home_id');
        (new StudentModel())->update($sid, ['host_home_id' => $hid ?: null]);

        return redirect()->to('/housing')->with('success', 'Assignment saved.');
    }

    private function collect(): array
    {
        $p = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;

        return ['family_name' => (string) $p('family_name'), 'address' => $p('address'), 'notes' => $p('notes')];
    }
}
