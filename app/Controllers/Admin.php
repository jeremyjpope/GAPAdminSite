<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\MoodleClient;
use App\Models\ProgramYearModel;
use App\Models\UserAreaModel;
use App\Models\UserModel;
use RuntimeException;

class Admin extends BaseController
{
    public function users()
    {
        $users = (new UserModel())->orderBy('fullname')->findAll();
        $areas = [];
        foreach ((new UserAreaModel())->findAll() as $r) {
            $areas[$r['user_id']][] = $r['area'];
        }

        return view('admin/users', ['title' => 'Site access', 'users' => $users, 'userAreas' => $areas, 'areas' => config('Areas')->list]);
    }

    public function saveUser()
    {
        $um = new UserModel();
        $id = (int) $this->request->getPost('id');
        $data = [
            'fullname'      => trim((string) $this->request->getPost('fullname')),
            'email'         => strtolower(trim((string) $this->request->getPost('email'))) ?: null,
            'username'      => strtolower(trim((string) $this->request->getPost('username'))) ?: null,
            'moodle_userid' => (int) $this->request->getPost('moodle_userid') ?: null,
            'is_superadmin' => Auth::isSuper() ? (int) (bool) $this->request->getPost('is_superadmin') : 0,
            'is_active'     => (int) (bool) $this->request->getPost('is_active'),
        ];
        if (! $data['email'] && ! $data['username'] && ! $data['moodle_userid']) {
            return redirect()->back()->with('error', 'Give at least a Moodle username or email so the login can be matched.');
        }
        if ($id === Auth::id()) {
            $data['is_superadmin'] = Auth::isSuper() ? 1 : 0; // never lock yourself out
            $data['is_active']     = 1;
        }
        if ($id) {
            $um->update($id, $data);
        } else {
            $um->insert($data);
            $id = $um->getInsertID();
        }
        $ua = new UserAreaModel();
        $ua->where('user_id', $id)->delete();
        foreach ((array) $this->request->getPost('areas') as $a) {
            if (isset(config('Areas')->list[$a])) {
                $ua->insert(['user_id' => $id, 'area' => $a]);
            }
        }

        return redirect()->to('/admin/users')->with('success', 'Access saved.');
    }

    public function deleteUser(int $id)
    {
        if ($id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot remove yourself.');
        }
        (new UserAreaModel())->where('user_id', $id)->delete();
        (new UserModel())->delete($id);

        return redirect()->to('/admin/users')->with('success', 'User removed.');
    }

    public function years()
    {
        return view('admin/years', ['title' => 'Program years', 'years' => all_years()]);
    }

    public function saveYear()
    {
        $m  = new ProgramYearModel();
        $id = (int) $this->request->getPost('id');
        $p  = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;
        $scale = $p('grade_scale');
        if ($scale !== null && json_decode($scale, true) === null) {
            return redirect()->back()->with('error', 'Grade scale must be valid JSON.');
        }
        $data = [
            'label' => (string) $p('label'), 'year_number' => (int) $p('year_number'),
            'start_date' => $p('start_date'), 'end_date' => $p('end_date'), 'week_one_starts' => $p('week_one_starts'),
            'moodle_overall_course_id' => (int) $p('moodle_overall_course_id') ?: null,
            'tuition_year_early' => $p('tuition_year_early'), 'tuition_year_late' => $p('tuition_year_late'), 'tuition_cutoff_date' => $p('tuition_cutoff_date'),
            'program_description' => $p('program_description'), 'transcript_notes' => $p('transcript_notes'),
            'grade_scale' => $scale, 'is_current' => (int) (bool) $this->request->getPost('is_current'),
        ];
        if ($data['is_current']) {
            $m->set(['is_current' => 0])->where('id >', 0)->update();
        }
        $id ? $m->update($id, $data) : $m->insert($data);

        return redirect()->to('/admin/years')->with('success', 'Program year saved.');
    }

    /** Moodle connection check. */
    public function moodle()
    {
        $c = new MoodleClient();
        $info = $error = null;
        if ($c->hasServiceToken()) {
            try {
                $info = $c->call('core_webservice_get_site_info');
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin/moodle', ['title' => 'Moodle connection', 'cfg' => config('Moodle'), 'info' => $info, 'error' => $error]);
    }
}
