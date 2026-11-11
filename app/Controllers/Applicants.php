<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Models\ApplicantModel;
use App\Models\ApplicantScoreModel;
use App\Models\ApplicantStepModel;
use App\Models\NoteModel;
use App\Models\StudentContactModel;
use App\Models\StudentModel;
use App\Models\UserAreaModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Applicants as Cfg;

/** Admissions: applicants for the selected year, their checklist, rubric scores, and acceptance into the program. */
class Applicants extends BaseController
{
    public function index()
    {
        $cfg    = new Cfg();
        $year   = current_year();
        $status = (string) $this->request->getGet('status');
        $sex    = (string) $this->request->getGet('sex');
        $q      = trim((string) $this->request->getGet('q'));
        $m      = new ApplicantModel();
        $b      = $m->orderBy('last_name')->orderBy('first_name');
        if ($q !== '') {
            // a name search looks across every year, so re-applicants and past declines turn up
            $b->groupStart()->like('first_name', $q)->orLike('last_name', $q)->orLike('preferred_name', $q)->orLike('email', $q)->groupEnd();
        } else {
            $b->where('program_year_id', $year['id'] ?? 0);
        }
        if ($status !== '' && isset($cfg->statuses[$status])) {
            $b->where('status', $status);
        }
        if (in_array($sex, ['M', 'F'], true)) {
            $b->where('gender', $sex);
        }
        $rows  = $b->findAll();
        $years = array_column(all_years(), 'label', 'id');
        $ids   = array_column($rows, 'id') ?: [0];

        // checklist progress: done steps per applicant, and the last step done
        $done  = [];
        foreach ((new ApplicantStepModel())->whereIn('applicant_id', $ids)->where('done', 1)->findAll() as $s) {
            $done[$s['applicant_id']][$s['step']] = $s;
        }
        $avg   = $this->averages($ids);
        $notes = (new NoteModel())->countsFor('applicant', $ids);
        $counts = [];
        foreach ($m->select('status, COUNT(*) AS n')->where('program_year_id', $year['id'] ?? 0)->groupBy('status')->findAll() as $c) {
            $counts[$c['status']] = (int) $c['n'];
        }

        return view('applicants/index', ['title' => 'Applicants', 'year' => $year, 'rows' => $rows, 'years' => $years, 'done' => $done, 'avg' => $avg, 'notes' => $notes,
            'counts' => $counts, 'cfg' => $cfg, 'status' => $status, 'sex' => $sex, 'q' => $q]);
    }

    /** The interview rubric matrix for the year: applicants × (category × scorer), averages ignore empty cells. */
    public function rubric()
    {
        $cfg  = new Cfg();
        $year = current_year();
        $rows = (new ApplicantModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('gender', 'DESC')->orderBy('submission_order')->orderBy('last_name')->findAll();
        $ids  = array_column($rows, 'id') ?: [0];
        $scores = (new ApplicantScoreModel())->whereIn('applicant_id', $ids)->findAll();
        $scorers = $this->scorers($scores);
        $matrix = [];   // applicant => category => scorer key => score
        foreach ($scores as $s) {
            $matrix[$s['applicant_id']][$s['category']][$this->scorerKey($s)] = (int) $s['score'];
        }

        return view('applicants/rubric', ['title' => 'Interview rubric ' . ($year['label'] ?? ''), 'year' => $year, 'rows' => $rows, 'scorers' => $scorers, 'matrix' => $matrix, 'avg' => $this->averages($ids), 'cfg' => $cfg]);
    }

    public function create()
    {
        return view('applicants/form', ['title' => 'New applicant', 'cfg' => new Cfg(), 'year' => current_year()]);
    }

    public function store()
    {
        $data = $this->data();
        if ($data['first_name'] === '' || $data['last_name'] === '') {
            return redirect()->back()->withInput()->with('error', 'First and last name are required.');
        }
        $data['program_year_id'] = current_year()['id'];
        $m = new ApplicantModel();
        // link to an earlier application by the same person (same email, or same name)
        $prior = $data['email'] ? $m->where('LOWER(email)', strtolower($data['email']))->orderBy('id', 'DESC')->first() : null;
        $prior ??= $m->where('LOWER(first_name)', strtolower($data['first_name']))->where('LOWER(last_name)', strtolower($data['last_name']))->orderBy('id', 'DESC')->first();
        $data['prior_applicant_id'] = $prior['id'] ?? null;
        $m->insert($data);
        $id = $m->getInsertID();
        (new ApplicantStepModel())->insert(['applicant_id' => $id, 'step' => 'applied', 'done' => 1, 'done_on' => date('Y-m-d')]);

        return redirect()->to('/applicants/' . $id)->with('success', 'Applicant added.' . ($prior ? ' Linked to an earlier application.' : ''));
    }

    public function show(int $id)
    {
        $cfg = new Cfg();
        $a   = $this->find($id);
        $steps = array_column((new ApplicantStepModel())->where('applicant_id', $id)->findAll(), null, 'step');
        $scores  = (new ApplicantScoreModel())->where('applicant_id', $id)->findAll();
        $scorers = $this->scorers($scores);
        $matrix  = [];
        foreach ($scores as $s) {
            $matrix[$s['category']][$this->scorerKey($s)] = (int) $s['score'];
        }
        $mine = [];
        foreach ($scores as $s) {
            if ($s['user_id'] && $s['user_id'] == Auth::id()) {
                $mine[$s['category']] = (int) $s['score'];
            }
        }
        $student = $a['student_id'] ? (new StudentModel())->find($a['student_id']) : null;
        $prior   = $a['prior_applicant_id'] ? (new ApplicantModel())->find($a['prior_applicant_id']) : null;
        $later   = (new ApplicantModel())->where('prior_applicant_id', $id)->findAll();
        $years   = array_column(all_years(), 'label', 'id');
        $yearRow = (new \App\Models\ProgramYearModel())->find($a['program_year_id']);

        return view('applicants/show', ['title' => trim($a['first_name'] . ' ' . $a['last_name']), 'a' => $a, 'steps' => $steps, 'scorers' => $scorers, 'matrix' => $matrix, 'mine' => $mine,
            'avg' => $this->averages([$id])[$id] ?? null, 'student' => $student, 'prior' => $prior, 'later' => $later, 'years' => $years, 'yearRow' => $yearRow,
            'notes' => (new NoteModel())->forSubject('applicant', $id), 'cfg' => $cfg]);
    }

    public function edit(int $id)
    {
        return view('applicants/form', ['title' => 'Edit applicant', 'a' => $this->find($id), 'cfg' => new Cfg(), 'year' => current_year()]);
    }

    public function update(int $id)
    {
        $this->find($id);
        $data = $this->data();
        if ($data['first_name'] === '' || $data['last_name'] === '') {
            return redirect()->back()->withInput()->with('error', 'First and last name are required.');
        }
        (new ApplicantModel())->update($id, $data);

        return redirect()->to('/applicants/' . $id)->with('success', 'Saved.');
    }

    public function delete(int $id)
    {
        $a = $this->find($id);
        if ($a['student_id']) {
            return redirect()->back()->with('error', 'This applicant was accepted and has a student record; mark them withdrawn instead.');
        }
        (new ApplicantStepModel())->where('applicant_id', $id)->delete();
        (new ApplicantScoreModel())->where('applicant_id', $id)->delete();
        (new NoteModel())->where('subject_type', 'applicant')->where('subject_id', $id)->delete();
        (new ApplicantModel())->delete($id);

        return redirect()->to('/applicants')->with('success', 'Applicant removed.');
    }

    public function setStatus(int $id)
    {
        $this->find($id);
        $status = (string) $this->request->getPost('status');
        if (! isset((new Cfg())->statuses[$status])) {
            return redirect()->back()->with('error', 'Unknown status.');
        }
        if ($status === 'accepted') {
            return redirect()->to('/applicants/' . $id)->with('error', 'Use the Accept button: it also creates the student record.');
        }
        (new ApplicantModel())->update($id, ['status' => $status]);

        return redirect()->to('/applicants/' . $id)->with('success', 'Status set to ' . (new Cfg())->statuses[$status][0] . '.');
    }

    /** Save the whole checklist at once (done flag, date, note per step). */
    public function saveSteps(int $id)
    {
        $this->find($id);
        $cfg = new Cfg();
        $m   = new ApplicantStepModel();
        $have = array_column($m->where('applicant_id', $id)->findAll(), null, 'step');
        $post = $this->request->getPost('steps') ?? [];
        foreach ($cfg->steps as $k => $label) {
            $in   = $post[$k] ?? [];
            $row  = ['applicant_id' => $id, 'step' => $k, 'done' => ! empty($in['done']) ? 1 : 0,
                'done_on' => ! empty($in['done_on']) && strtotime($in['done_on']) ? $in['done_on'] : null,
                'note'    => trim((string) ($in['note'] ?? '')) ?: null];
            if ($row['done_on'] && ! $row['done']) {
                $row['done'] = 1;
            }
            if (isset($have[$k])) {
                $m->update($have[$k]['id'], $row);
            } elseif ($row['done'] || $row['note']) {
                $m->insert($row);
            }
        }

        return redirect()->to('/applicants/' . $id . '#checklist')->with('success', 'Checklist saved.');
    }

    /** The logged-in user's four rubric scores; a blank score is removed, never counted as zero. */
    public function saveScores(int $id)
    {
        $this->find($id);
        $cfg = new Cfg();
        $m   = new ApplicantScoreModel();
        $uid = Auth::id();
        foreach ($cfg->rubric as $k => $r) {
            $v   = $this->request->getPost('score')[$k] ?? '';
            $row = $m->where('applicant_id', $id)->where('user_id', $uid)->where('category', $k)->first();
            if ($v === '' || $v === null) {
                if ($row) {
                    $m->delete($row['id']);
                }
                continue;
            }
            $v = max(1, min(10, (int) $v));
            $row ? $m->update($row['id'], ['score' => $v, 'updated_at' => date('Y-m-d H:i:s')])
                 : $m->insert(['applicant_id' => $id, 'user_id' => $uid, 'category' => $k, 'score' => $v, 'updated_at' => date('Y-m-d H:i:s')]);
        }

        return redirect()->to('/applicants/' . $id . '#rubric')->with('success', 'Your scores are saved.');
    }

    /** Accept: create the student record for the applicant's year (once), copy the details, link, and mark accepted. */
    public function accept(int $id)
    {
        $a  = $this->find($id);
        $am = new ApplicantModel();
        $sm = new StudentModel();
        if ($a['student_id'] && $sm->find($a['student_id'])) {
            $am->update($id, ['status' => 'accepted']);

            return redirect()->to('/students/' . $a['student_id'])->with('success', 'Already a student; status set to accepted.');
        }
        // reuse a student row that already exists for this person in that year (e.g. synced from Moodle)
        $existing = $a['email'] ? $sm->where('program_year_id', $a['program_year_id'])->where('LOWER(email)', strtolower($a['email']))->first() : null;
        $existing ??= $sm->where('program_year_id', $a['program_year_id'])->where('LOWER(first_name)', strtolower($a['first_name']))->where('LOWER(last_name)', strtolower($a['last_name']))->first();
        $copy = ['first_name', 'last_name', 'preferred_name', 'email', 'phone', 'gender', 'birthday', 'address', 'tshirt_size', 'placement_preference', 'allergies_medical', 'cleared_to_drive', 'auto_insurance', 'housing_preference'];
        if ($existing) {
            $upd = [];
            foreach ($copy as $k) {
                if (($existing[$k] === null || $existing[$k] === '') && $a[$k] !== null && $a[$k] !== '') {
                    $upd[$k] = $a[$k];
                }
            }
            if ($upd) {
                $sm->update($existing['id'], $upd);
            }
            $sid = $existing['id'];
        } else {
            $row = ['program_year_id' => $a['program_year_id'], 'status' => 'enrolled'];
            foreach ($copy as $k) {
                $row[$k] = $a[$k];
            }
            $sm->insert($row);
            $sid = $sm->getInsertID();
        }
        $cm = new StudentContactModel();
        foreach (['mom_email' => 'Mother', 'dad_email' => 'Father'] as $k => $rel) {
            if ($a[$k] && ! $cm->where('student_id', $sid)->where('LOWER(email)', strtolower($a[$k]))->first()) {
                $cm->insert(['student_id' => $sid, 'relation' => $rel, 'email' => $a[$k]]);
            }
        }
        $am->update($id, ['status' => 'accepted', 'student_id' => $sid]);
        $stm = new ApplicantStepModel();
        if (! $stm->where('applicant_id', $id)->where('step', 'decision')->first()) {
            $stm->insert(['applicant_id' => $id, 'step' => 'decision', 'done' => 1, 'done_on' => date('Y-m-d'), 'note' => 'Accepted in the admin site by ' . (Auth::user()['fullname'] ?? '')]);
        }

        return redirect()->to('/students/' . $sid)->with('success', 'Accepted. ' . ($existing ? 'Linked to the existing student record.' : 'Student record created; add the Moodle account and sync when ready.'));
    }

    // ------------------------------------------------------------ internals

    private function find(int $id): array
    {
        $a = (new ApplicantModel())->find($id);
        if (! $a) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $a;
    }

    private ?array $userByFirst = null;

    /** Scorer key: the site user id; an imported score keyed by first name joins the site user with that first name when there is exactly one. */
    private function scorerKey(array $s): string
    {
        if ($s['user_id']) {
            return (string) $s['user_id'];
        }
        if ($this->userByFirst === null) {
            $this->userByFirst = [];
            foreach ((new UserModel())->findAll() as $u) {
                $f = strtolower(trim(explode(' ', trim($u['fullname']))[0]));
                $this->userByFirst[$f] = isset($this->userByFirst[$f]) ? false : $u;   // false = ambiguous
            }
        }
        $u = $this->userByFirst[strtolower(trim((string) $s['scorer_name']))] ?? null;

        return $u ? (string) $u['id'] : 'n:' . $s['scorer_name'];
    }

    /** Scorers present in a set of scores: key => ['fullname' => …], site users first then imported names. */
    private function scorers(array $scores): array
    {
        $out = [];
        $keys = array_unique(array_map(fn ($s) => $this->scorerKey($s), $scores));
        $ids  = array_values(array_filter($keys, static fn ($k) => ! str_starts_with($k, 'n:')));
        if ($ids) {
            foreach ((new UserModel())->whereIn('id', $ids)->orderBy('fullname')->findAll() as $u) {
                $out[(string) $u['id']] = $u;
            }
        }
        $names = array_filter($keys, static fn ($k) => str_starts_with($k, 'n:'));
        sort($names);
        foreach ($names as $k) {
            $out[$k] = ['fullname' => substr($k, 2)];
        }

        return $out;
    }

    /** Average of every entered score per applicant, plus per-category averages: [id => ['total' => x, 'cats' => [k => x], 'n' => scorers]]. */
    private function averages(array $ids): array
    {
        $out = [];
        if (! $ids) {
            return $out;
        }
        $sum = [];
        foreach ((new ApplicantScoreModel())->whereIn('applicant_id', $ids)->findAll() as $s) {
            $sum[$s['applicant_id']]['all'][]               = (int) $s['score'];
            $sum[$s['applicant_id']]['cat'][$s['category']][] = (int) $s['score'];
            $sum[$s['applicant_id']]['users'][$this->scorerKey($s)] = true;
        }
        foreach ($sum as $id => $d) {
            $cats = [];
            foreach ($d['cat'] as $k => $v) {
                $cats[$k] = array_sum($v) / count($v);
            }
            $out[$id] = ['total' => array_sum($d['all']) / count($d['all']), 'cats' => $cats, 'n' => count($d['users'])];
        }

        return $out;
    }

    private function data(): array
    {
        $cfg = new Cfg();
        $p   = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;
        $status = (string) $p('status');
        $link   = $p('mp_link');
        if ($link && ! preg_match('#^https?://#i', $link)) {
            $link = 'https://' . $link;
        }
        $data = [
            'submission_order' => (int) $p('submission_order') ?: null,
            'first_name' => (string) $p('first_name'), 'last_name' => (string) $p('last_name'), 'preferred_name' => $p('preferred_name'),
            'gender'     => in_array($p('gender'), ['M', 'F'], true) ? $p('gender') : null,
            'email' => $p('email'), 'phone' => $p('phone'), 'address' => $p('address'),
            'birthday' => $p('birthday') && strtotime($p('birthday')) ? $p('birthday') : null,
            'mom_email' => $p('mom_email'), 'dad_email' => $p('dad_email'),
            'tshirt_size' => $p('tshirt_size'), 'placement_preference' => $p('placement_preference'), 'allergies_medical' => $p('allergies_medical'),
            'cleared_to_drive' => $p('cleared_to_drive'), 'auto_insurance' => $p('auto_insurance'), 'housing_preference' => $p('housing_preference'),
            'tuition_note' => $p('tuition_note'), 'mp_link' => $link, 'notes' => $p('notes'),
        ];
        if (isset($cfg->statuses[$status]) && $status !== 'accepted') {
            $data['status'] = $status;
        }

        return $data;
    }
}
