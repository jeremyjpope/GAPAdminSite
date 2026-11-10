<?php

namespace App\Controllers;

use App\Libraries\MoodleClient;
use App\Models\StudentModel;
use App\Models\TranscriptCourseModel;
use App\Models\TranscriptGradeModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

class Transcripts extends BaseController
{
    public function index()
    {
        $year     = current_year();
        $students = (new StudentModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('last_name')->findAll();
        $courses  = (new TranscriptCourseModel())->where('program_year_id', $year['id'] ?? 0)->countAllResults();
        $counts   = [];
        foreach ((new TranscriptGradeModel())->select('student_id, COUNT(*) AS n')->whereIn('student_id', array_column($students, 'id') ?: [0])->where('letter IS NOT NULL')->where("letter != ''")->groupBy('student_id')->findAll() as $r) {
            $counts[$r['student_id']] = (int) $r['n'];
        }

        return view('transcripts/index', ['title' => 'Transcripts', 'year' => $year, 'students' => $students, 'courseCount' => $courses, 'counts' => $counts]);
    }

    public function catalog()
    {
        $year    = current_year();
        $courses = (new TranscriptCourseModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('sort_order')->orderBy('id')->findAll();
        $moodleCourses = [];
        $moodleError   = null;
        try {
            $moodleCourses = (new MoodleClient())->courses();
        } catch (RuntimeException $e) {
            $moodleError = $e->getMessage();
        }

        return view('transcripts/catalog', ['title' => 'Transcript catalog', 'year' => $year, 'courses' => $courses, 'moodleCourses' => $moodleCourses, 'moodleError' => $moodleError]);
    }

    public function saveCourse()
    {
        $m  = new TranscriptCourseModel();
        $id = (int) $this->request->getPost('id');
        $p  = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;
        $data = [
            'pillar' => $p('pillar'), 'name' => (string) $p('name'), 'description' => $p('description'), 'term' => $p('term'),
            'hours' => (int) $p('hours'), 'sort_order' => (int) $p('sort_order'),
            'moodle_course_id' => $p('moodle_course_id') ? (int) $p('moodle_course_id') : null,
        ];
        if ($id) {
            $m->update($id, $data);
        } else {
            $data['program_year_id'] = current_year()['id'];
            $m->insert($data);
        }

        return redirect()->to('/transcripts/catalog')->with('success', 'Course saved.');
    }

    public function deleteCourse(int $id)
    {
        (new TranscriptGradeModel())->where('transcript_course_id', $id)->delete();
        (new TranscriptCourseModel())->delete($id);

        return redirect()->to('/transcripts/catalog')->with('success', 'Course removed.');
    }

    public function student(int $id)
    {
        [$s, $year, $courses, $grades] = $this->load($id);

        return view('transcripts/student', ['title' => 'Transcript: ' . student_name($s), 's' => $s, 'year' => $year, 'courses' => $courses, 'grades' => $grades, 'summary' => $this->summary($courses, $grades, $year)]);
    }

    public function saveGrades(int $id)
    {
        [$s, $year, $courses] = $this->load($id);
        $gm      = new TranscriptGradeModel();
        $letters = (array) $this->request->getPost('letter');
        foreach ($courses as $c) {
            $letter = strtoupper(trim((string) ($letters[$c['id']] ?? '')));
            $row    = $gm->where('student_id', $id)->where('transcript_course_id', $c['id'])->first();
            $data   = ['student_id' => $id, 'transcript_course_id' => $c['id'], 'letter' => $letter ?: null, 'source' => 'manual', 'updated_at' => date('Y-m-d H:i:s')];
            if ($row) {
                if (($row['letter'] ?? '') !== $letter) {
                    $gm->update($row['id'], $data + ['percent' => null]);
                }
            } elseif ($letter !== '') {
                $gm->insert($data);
            }
        }
        $sm = new StudentModel();
        $sm->update($id, [
            'status'        => $this->request->getPost('status') ?: $s['status'],
            'attended_from' => trim((string) $this->request->getPost('attended_from')) ?: null,
            'attended_to'   => trim((string) $this->request->getPost('attended_to')) ?: null,
        ]);

        return redirect()->to('/transcripts/' . $id)->with('success', 'Grades saved.');
    }

    /** Pull course totals from Moodle for every catalog line mapped to a Moodle course. */
    public function pullMoodle(int $id)
    {
        [$s, $year, $courses] = $this->load($id);
        if (! $s['moodle_userid']) {
            return redirect()->back()->with('error', 'Student is not linked to a Moodle account.');
        }
        try {
            $totals = (new MoodleClient())->userCourseTotals((int) $s['moodle_userid']);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        $gm = new TranscriptGradeModel();
        $n  = 0;
        foreach ($courses as $c) {
            $cid = (int) ($c['moodle_course_id'] ?? 0);
            if (! $cid || ! isset($totals[$cid])) {
                continue;
            }
            $pct = $this->toPercent($totals[$cid]);
            if ($pct === null) {
                continue;
            }
            $data = ['student_id' => $id, 'transcript_course_id' => $c['id'], 'percent' => $pct, 'letter' => letter_for($pct, $year), 'source' => 'moodle', 'updated_at' => date('Y-m-d H:i:s')];
            $row  = $gm->where('student_id', $id)->where('transcript_course_id', $c['id'])->first();
            $row ? $gm->update($row['id'], $data) : $gm->insert($data);
            $n++;
        }

        return redirect()->to('/transcripts/' . $id)->with('success', "Pulled {$n} grade(s) from Moodle. Lines without a mapped Moodle course were left alone.");
    }

    public function pdf(int $id)
    {
        [$s, $year, $courses, $grades] = $this->load($id);
        $html = view('transcripts/pdf', [
            's' => $s, 'year' => $year, 'courses' => $courses, 'grades' => $grades,
            'summary' => $this->summary($courses, $grades, $year),
            'issued'  => date('F j, Y'),
            'logo'    => $this->logoDataUri(),
        ]);

        $opt = new Options();
        $opt->set('isRemoteEnabled', false);
        $opt->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html);
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();

        $file = preg_replace('/[^A-Za-z0-9_]+/', '_', $s['first_name'] . '_' . $s['last_name'] . '_GAP_Transcript_' . $year['label']) . '.pdf';
        $download = $this->request->getGet('download') !== null;

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', ($download ? 'attachment' : 'inline') . '; filename="' . $file . '"')
            ->setBody($pdf->output());
    }

    // ---- helpers ----

    private function load(int $id): array
    {
        $s = (new StudentModel())->find($id);
        if (! $s) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $year    = (new \App\Models\ProgramYearModel())->find($s['program_year_id']) ?? current_year();
        $courses = (new TranscriptCourseModel())->where('program_year_id', $year['id'])->orderBy('sort_order')->orderBy('id')->findAll();
        $grades  = array_column((new TranscriptGradeModel())->where('student_id', $id)->findAll(), null, 'transcript_course_id');

        return [$s, $year, $courses, $grades];
    }

    /** Courses completed, hours, GPA (hours-weighted like the sample's cumulative GPA). */
    private function summary(array $courses, array $grades, array $year): array
    {
        $n = $hours = 0;
        $pts = $ptsHours = 0.0;
        foreach ($courses as $c) {
            $g = $grades[$c['id']] ?? null;
            if (! $g || ! $g['letter']) {
                continue;
            }
            $n++;
            $hours += (int) $c['hours'];
            $p = points_for($g['letter'], $year);
            if ($p !== null) {
                $pts      += $p * max(1, (int) $c['hours']);
                $ptsHours += max(1, (int) $c['hours']);
            }
        }

        return ['courses' => $n, 'hours' => $hours, 'gpa' => $ptsHours ? round($pts / $ptsHours, 2) : null];
    }

    private function toPercent(array $t): ?float
    {
        $raw = $t['rawgrade'] ?? null;
        if ($raw !== null && $raw !== '' && is_numeric($raw)) {
            return (float) $raw;           // course totals are configured 0-100 in this gradebook
        }
        $g = trim((string) ($t['grade'] ?? ''));
        if ($g === '' || $g === '-') {
            return null;
        }
        if (preg_match('/([\d.]+)\s*%/', $g, $m)) {
            return (float) $m[1];
        }

        return is_numeric($g) ? (float) $g : null;
    }

    private function logoDataUri(): ?string
    {
        $p = FCPATH . 'img/gap-logo.png';

        return is_file($p) ? 'data:image/png;base64,' . base64_encode(file_get_contents($p)) : null;
    }
}
