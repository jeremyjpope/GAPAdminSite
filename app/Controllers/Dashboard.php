<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Models\HostHomeModel;
use App\Models\VolunteerModel;
use App\Models\PaymentModel;
use App\Models\ScheduleEntryModel;
use App\Models\ScheduleSlotModel;
use App\Models\CourseModel;
use App\Models\ProgramYearModel;
use App\Models\StudentModel;
use App\Models\TuitionAccountModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $year = current_year();
        $yid  = $year['id'] ?? 0;

        $students = (new StudentModel())->where('program_year_id', $yid)->orderBy('last_name')->findAll();
        $ids      = array_column($students, 'id') ?: [0];

        $due  = (new TuitionAccountModel())->whereIn('student_id', $ids)->selectSum('total_due')->first()['total_due'] ?? 0;
        $paid = (new PaymentModel())->whereIn('student_id', $ids)->where('paid_on <=', date('Y-m-d'))->selectSum('amount')->first()['amount'] ?? 0;

        $recent = (new PaymentModel())->whereIn('student_id', $ids)->orderBy('paid_on', 'DESC')->orderBy('id', 'DESC')->limit(8)->findAll();
        $byId   = array_column($students, null, 'id');

        // this week's sessions (Mon-Sun) for the schedule panel
        $mon   = date('Y-m-d', strtotime('monday this week'));
        $sun   = date('Y-m-d', strtotime($mon . ' +6 days'));
        $week  = Auth::can('schedule') ? (new ScheduleEntryModel())->where('program_year_id', $yid)->where('start_date <=', $sun)->where('end_date >=', $mon)->where('status !=', 'cancelled')->orderBy('start_date')->orderBy('slot_id')->findAll() : [];
        $slots = array_column((new ScheduleSlotModel())->where('program_year_id', $yid)->orderBy('sort_order')->findAll(), null, 'id');
        $courses = $week ? array_column((new CourseModel())->findAll(), null, 'id') : [];

        return view('dashboard', [
            'week'        => $week,
            'weekMon'     => $mon,
            'slots'       => $slots,
            'courses'     => $courses,
            'title'       => 'Dashboard',
            'year'        => $year,
            'students'    => $students,
            'unhoused'    => count(array_filter($students, fn ($s) => empty($s['host_home_id']))),
            'unmentored'  => count(array_filter($students, fn ($s) => empty($s['mentor_id']))),
            'hostHomes'   => (new HostHomeModel())->where('program_year_id', $yid)->countAllResults(),
            'mentors'     => count((new VolunteerModel())->withRoles(config('Volunteers')->mentorRoles)),
            'tuitionDue'  => (float) $due,
            'tuitionPaid' => (float) $paid,
            'recent'      => $recent,
            'byId'        => $byId,
        ]);
    }

    public function switchYear()
    {
        $id = (int) $this->request->getPost('year_id');
        if ((new ProgramYearModel())->find($id)) {
            session()->set('year_id', $id);
        }

        return redirect()->back();
    }
}
