<?php

namespace App\Controllers;

use App\Libraries\MoodleClient;
use App\Models\HostHomeModel;
use App\Models\VolunteerModel;
use App\Models\NoteModel;
use App\Models\StudentContactModel;
use App\Models\StudentModel;
use App\Models\StudentVehicleModel;
use App\Models\TuitionAccountModel;
use App\Models\PaymentModel;
use RuntimeException;

class Students extends BaseController
{
    private StudentModel $students;

    public function __construct()
    {
        $this->students = new StudentModel();
    }

    public function index()
    {
        $year = current_year();
        $q    = trim((string) $this->request->getGet('q'));
        $b    = $this->students->where('program_year_id', $year['id'] ?? 0);
        if ($q !== '') {
            $b->groupStart()->like('first_name', $q)->orLike('last_name', $q)->orLike('preferred_name', $q)->orLike('email', $q)->groupEnd();
        }
        $rows = $b->orderBy('last_name')->orderBy('first_name')->findAll();

        $homes   = array_column((new HostHomeModel())->findAll(), 'family_name', 'id');
        $mentors = [];
        foreach ((new VolunteerModel())->findAll() as $v) {
            $mentors[$v['id']] = $v['first_name'] . ' ' . $v['last_name'];
        }

        return view('students/index', [
            'title' => 'Students', 'students' => $rows, 'q' => $q, 'homes' => $homes, 'mentors' => $mentors, 'year' => $year,
        ]);
    }

    public function create()
    {
        return view('students/form', ['title' => 'New student', 'student' => null] + $this->formLists());
    }

    public function store()
    {
        $data = $this->collect();
        $data['program_year_id'] = current_year()['id'];
        if (! $this->students->insert($data)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->students->errors()));
        }

        return redirect()->to('/students/' . $this->students->getInsertID())->with('success', 'Student added.');
    }

    public function show(int $id)
    {
        $s = $this->mustFind($id);
        $acct = (new TuitionAccountModel())->where('student_id', $id)->first();
        $paid = (float) ((new PaymentModel())->where('student_id', $id)->where('paid_on <=', date('Y-m-d'))->selectSum('amount')->first()['amount'] ?? 0);

        return view('students/show', [
            'title'    => student_name($s),
            's'        => $s,
            'contacts' => (new StudentContactModel())->where('student_id', $id)->findAll(),
            'vehicles' => (new StudentVehicleModel())->where('student_id', $id)->findAll(),
            'home'     => $s['host_home_id'] ? (new HostHomeModel())->find($s['host_home_id']) : null,
            'mentor'   => $s['mentor_id'] ? (new VolunteerModel())->find($s['mentor_id']) : null,
            'acct'     => $acct,
            'paid'     => $paid,
            'moodleUrl' => (new MoodleClient())->baseUrl(),
            'notes'    => (new NoteModel())->forSubject('student', $id),
        ]);
    }

    public function edit(int $id)
    {
        $s = $this->mustFind($id);

        return view('students/form', ['title' => 'Edit ' . student_name($s), 'student' => $s] + $this->formLists());
    }

    public function update(int $id)
    {
        $this->mustFind($id);
        if (! $this->students->update($id, $this->collect())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->students->errors()));
        }

        return redirect()->to('/students/' . $id)->with('success', 'Saved.');
    }

    public function uploadPhoto(int $id)
    {
        $s    = $this->mustFind($id);
        $file = $this->request->getFile('photo');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', 'No photo uploaded.');
        }
        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'image/heic'], true)) {
            return redirect()->back()->with('error', 'Photo must be a JPEG, PNG or WebP image.');
        }
        $name = $id . '_' . time() . '.' . ($file->guessExtension() ?: 'jpg');
        $file->move(WRITEPATH . 'uploads/photos', $name, true);

        // Resize to a sane size for the directory / transcript.
        try {
            service('image')->withFile(WRITEPATH . 'uploads/photos/' . $name)->fit(600, 750, 'center')->save(WRITEPATH . 'uploads/photos/' . $name);
        } catch (\Throwable $e) {
            log_message('warning', 'Photo resize failed: ' . $e->getMessage());
        }

        if ($s['photo_path'] && is_file(WRITEPATH . 'uploads/photos/' . $s['photo_path'])) {
            @unlink(WRITEPATH . 'uploads/photos/' . $s['photo_path']);
        }
        $this->students->update($id, ['photo_path' => $name]);

        return redirect()->to('/students/' . $id)->with('success', 'Photo updated.');
    }

    /** Serve a student photo from writable/ (never web-exposed directly). */
    public function photo(int $id)
    {
        $s = $this->students->find($id);
        $path = $s && $s['photo_path'] ? WRITEPATH . 'uploads/photos/' . basename($s['photo_path']) : null;
        if (! $path || ! is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('');
        }

        return $this->response->setHeader('Content-Type', mime_content_type($path))->setHeader('Cache-Control', 'private, max-age=3600')->setBody(file_get_contents($path));
    }

    public function addContact(int $id)
    {
        $this->mustFind($id);
        (new StudentContactModel())->insert([
            'student_id' => $id,
            'relation'   => trim((string) $this->request->getPost('relation')),
            'name'       => trim((string) $this->request->getPost('name')),
            'email'      => trim((string) $this->request->getPost('email')),
            'phone'      => trim((string) $this->request->getPost('phone')),
            'notes'      => trim((string) $this->request->getPost('notes')),
        ]);

        return redirect()->to('/students/' . $id)->with('success', 'Contact added.');
    }

    public function deleteContact(int $id, int $cid)
    {
        (new StudentContactModel())->where('student_id', $id)->delete($cid);

        return redirect()->to('/students/' . $id)->with('success', 'Contact removed.');
    }

    public function addVehicle(int $id)
    {
        $this->mustFind($id);
        (new StudentVehicleModel())->insert([
            'student_id'  => $id,
            'description' => trim((string) $this->request->getPost('description')),
            'vin'         => strtoupper(trim((string) $this->request->getPost('vin'))),
            'oil_type'    => trim((string) $this->request->getPost('oil_type')),
            'oil_notes'   => trim((string) $this->request->getPost('oil_notes')),
        ]);

        return redirect()->to('/students/' . $id)->with('success', 'Vehicle added.');
    }

    public function deleteVehicle(int $id, int $vid)
    {
        (new StudentVehicleModel())->where('student_id', $id)->delete($vid);

        return redirect()->to('/students/' . $id)->with('success', 'Vehicle removed.');
    }

    /** Live view of the student's Moodle gradebook for the year's overall-standing course. */
    public function grades(int $id)
    {
        $s    = $this->mustFind($id);
        $year = current_year();
        $data = ['title' => 'Moodle grades: ' . student_name($s), 's' => $s, 'items' => [], 'error' => null, 'year' => $year];
        try {
            if (! $s['moodle_userid']) {
                throw new RuntimeException('This student is not linked to a Moodle account yet (use "Sync from Moodle" or set the Moodle user id).');
            }
            if (empty($year['moodle_overall_course_id'])) {
                throw new RuntimeException('No overall-standing course id is set for this program year (Site Admin > Program years).');
            }
            $r = (new MoodleClient())->userGradeItems((int) $year['moodle_overall_course_id'], (int) $s['moodle_userid']);
            $data['items'] = $r['items'];
        } catch (RuntimeException $e) {
            $data['error'] = $e->getMessage();
        }

        return view('students/grades', $data);
    }

    /** Create/link students from the Moodle student cohort. */
    public function syncMoodle()
    {
        $year = current_year();
        if ($this->request->getMethod() !== 'POST') {
            return view('students/sync', ['title' => 'Sync from Moodle', 'year' => $year]);
        }
        if (empty($year['moodle_overall_course_id'])) {
            return redirect()->back()->with('error', 'Set the Moodle overall-standing course id for this program year first (Site admin > Program years).');
        }
        try {
            $members = (new MoodleClient())->enrolledStudents((int) $year['moodle_overall_course_id']);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $created = $linked = $skipped = 0;
        foreach ($members as $u) {
            $email = strtolower(trim($u['email'] ?? ''));
            $existing = $this->students->where('program_year_id', $year['id'])->where('moodle_userid', (int) $u['id'])->first();
            if (! $existing && $email !== '') {
                $existing = $this->students->where('program_year_id', $year['id'])->where('LOWER(email)', $email)->first();
            }
            if (! $existing) {
                $existing = $this->students->where('program_year_id', $year['id'])
                    ->where('LOWER(first_name)', strtolower($u['firstname'] ?? ''))
                    ->where('LOWER(last_name)', strtolower($u['lastname'] ?? ''))->first();
            }
            if ($existing) {
                if (empty($existing['moodle_userid'])) {
                    $this->students->update($existing['id'], ['moodle_userid' => (int) $u['id'], 'email' => $existing['email'] ?: $email]);
                    $linked++;
                } else {
                    $skipped++;
                }
                continue;
            }
            $this->students->insert([
                'program_year_id' => $year['id'],
                'moodle_userid'   => (int) $u['id'],
                'first_name'      => $u['firstname'] ?? '',
                'last_name'       => $u['lastname'] ?? '',
                'email'           => $email,
                'phone'           => $u['phone1'] ?? null,
                'address'         => trim(($u['address'] ?? '') . (isset($u['city']) ? ', ' . $u['city'] : '')) ?: null,
                'status'          => 'enrolled',
            ]);
            $created++;
        }

        return redirect()->to('/students')->with('success', "Moodle sync complete: {$created} created, {$linked} linked, {$skipped} already linked.");
    }

    // ---- helpers ----

    private function mustFind(int $id): array
    {
        $s = $this->students->find($id);
        if (! $s) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Student not found');
        }

        return $s;
    }

    private function formLists(): array
    {
        $yid = current_year()['id'] ?? 0;

        return [
            'homes'   => (new HostHomeModel())->where('program_year_id', $yid)->orderBy('family_name')->findAll(),
            'mentors' => (new VolunteerModel())->withRoles(config('Volunteers')->mentorRoles),
        ];
    }

    private function collect(): array
    {
        $p = fn (string $k) => ($v = trim((string) $this->request->getPost($k))) === '' ? null : $v;

        return [
            'first_name'           => (string) $p('first_name'),
            'last_name'            => (string) $p('last_name'),
            'preferred_name'       => $p('preferred_name'),
            'email'                => $p('email'),
            'phone'                => $p('phone'),
            'gender'               => $p('gender'),
            'birthday'             => $p('birthday'),
            'address'              => $p('address'),
            'tshirt_size'          => $p('tshirt_size'),
            'placement_preference' => $p('placement_preference'),
            'allergies_medical'    => $p('allergies_medical'),
            'cleared_to_drive'     => $p('cleared_to_drive'),
            'auto_insurance'       => $p('auto_insurance'),
            'housing_preference'   => $p('housing_preference'),
            'host_home_id'         => $p('host_home_id') ? (int) $p('host_home_id') : null,
            'mentor_id'            => $p('mentor_id') ? (int) $p('mentor_id') : null,
            'moodle_userid'        => $p('moodle_userid') ? (int) $p('moodle_userid') : null,
            'status'               => $p('status') ?? 'enrolled',
            'attended_from'        => $p('attended_from'),
            'attended_to'          => $p('attended_to'),
            'notes'                => $p('notes'),
        ];
    }
}
