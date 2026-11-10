<?php

namespace App\Controllers;

use App\Models\StudentModel;
use App\Models\VolunteerModel;

/**
 * Mentor assignments. A mentor is a volunteer with a Guys Mentor / Girls Mentor role;
 * people are added or edited in the Volunteers area.
 */
class Mentors extends BaseController
{
    public function index()
    {
        $year     = current_year();
        $students = (new StudentModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('last_name')->findAll();
        $assigned = array_values(array_unique(array_filter(array_column($students, 'mentor_id'))));
        $vm       = new VolunteerModel();
        $mentors  = $vm->withRoles(config('Volunteers')->mentorRoles, $assigned);
        $roles    = $vm->rolesFor(array_column($mentors, 'id'));
        $byMentor = [];
        foreach ($students as $s) {
            $byMentor[$s['mentor_id'] ?: 0][] = $s;
        }

        return view('mentors/index', ['title' => 'Mentors', 'year' => $year, 'mentors' => $mentors, 'roles' => $roles, 'students' => $students, 'byMentor' => $byMentor]);
    }

    public function assign()
    {
        $sid = (int) $this->request->getPost('student_id');
        $mid = (int) $this->request->getPost('mentor_id');
        (new StudentModel())->update($sid, ['mentor_id' => $mid ?: null]);

        return redirect()->to('/mentors')->with('success', 'Assignment saved.');
    }
}
