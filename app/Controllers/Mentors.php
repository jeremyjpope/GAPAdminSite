<?php

namespace App\Controllers;

use App\Models\MentorModel;
use App\Models\StudentModel;

class Mentors extends BaseController
{
    public function index()
    {
        $year     = current_year();
        $mentors  = (new MentorModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('name')->findAll();
        $students = (new StudentModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('last_name')->findAll();
        $byMentor = [];
        foreach ($students as $s) {
            $byMentor[$s['mentor_id'] ?: 0][] = $s;
        }

        return view('mentors/index', ['title' => 'Mentors', 'year' => $year, 'mentors' => $mentors, 'students' => $students, 'byMentor' => $byMentor]);
    }

    public function store()
    {
        $data = $this->collect();
        $data['program_year_id'] = current_year()['id'];
        (new MentorModel())->insert($data);

        return redirect()->to('/mentors')->with('success', 'Mentor added.');
    }

    public function update(int $id)
    {
        (new MentorModel())->update($id, $this->collect());

        return redirect()->to('/mentors')->with('success', 'Mentor saved.');
    }

    public function delete(int $id)
    {
        (new StudentModel())->where('mentor_id', $id)->set(['mentor_id' => null])->update();
        (new MentorModel())->delete($id);

        return redirect()->to('/mentors')->with('success', 'Mentor removed.');
    }

    public function assign()
    {
        $sid = (int) $this->request->getPost('student_id');
        $mid = (int) $this->request->getPost('mentor_id');
        (new StudentModel())->update($sid, ['mentor_id' => $mid ?: null]);

        return redirect()->to('/mentors')->with('success', 'Assignment saved.');
    }

    private function collect(): array
    {
        $p = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;

        return ['name' => (string) $p('name'), 'email' => $p('email'), 'phone' => $p('phone'), 'gender' => $p('gender'), 'notes' => $p('notes')];
    }
}
