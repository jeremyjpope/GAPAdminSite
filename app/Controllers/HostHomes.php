<?php

namespace App\Controllers;

use App\Models\HostHomeModel;
use App\Models\NoteModel;
use App\Models\StudentModel;

class HostHomes extends BaseController
{
    public function index()
    {
        $year  = current_year();
        $homes = (new HostHomeModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('family_name')->findAll();
        $students = (new StudentModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('last_name')->findAll();
        $byHome = [];
        foreach ($students as $s) {
            $byHome[$s['host_home_id'] ?: 0][] = $s;
        }

        $noteCounts = (new NoteModel())->countsFor('host_home', array_column($homes, 'id'));

        return view('housing/index', ['title' => 'Host Homes', 'year' => $year, 'homes' => $homes, 'students' => $students, 'byHome' => $byHome, 'noteCounts' => $noteCounts]);
    }

    public function store()
    {
        $data = $this->collect();
        $data['program_year_id'] = current_year()['id'];
        (new HostHomeModel())->insert($data);

        return redirect()->to('/housing')->with('success', 'Host home added.');
    }

    public function update(int $id)
    {
        (new HostHomeModel())->update($id, $this->collect());

        return redirect()->to('/housing')->with('success', 'Host home saved.');
    }

    public function delete(int $id)
    {
        (new StudentModel())->where('host_home_id', $id)->set(['host_home_id' => null])->update();
        (new HostHomeModel())->delete($id);

        return redirect()->to('/housing')->with('success', 'Host home removed.');
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

        return [
            'family_name' => (string) $p('family_name'), 'email' => $p('email'), 'email2' => $p('email2'),
            'phone' => $p('phone'), 'phone2' => $p('phone2'), 'address' => $p('address'), 'notes' => $p('notes'),
        ];
    }
}
