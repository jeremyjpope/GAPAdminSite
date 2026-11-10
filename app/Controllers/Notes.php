<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Models\HostHomeModel;
use App\Models\NoteModel;
use App\Models\StudentModel;
use App\Models\VolunteerModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Dated notes on a student or a host home. Access follows the subject's area
 * (students -> "students", host homes -> "housing"). Only the author or a super admin may edit/delete.
 */
class Notes extends BaseController
{
    /** Full-page note log for a subject. */
    public function index(string $type, int $id)
    {
        [$type, $subject, $label] = $this->subject($type, $id);

        return view('notes/index', [
            'title' => 'Notes: ' . $label, 'type' => $type, 'subject' => $subject, 'label' => $label,
            'notes' => (new NoteModel())->forSubject($type, $id),
            'backUrl' => $type === 'student' ? site_url('students/' . $id) : ($type === 'volunteer' ? site_url('volunteers/' . $id) : site_url('housing')),
        ]);
    }

    public function store(string $type, int $id)
    {
        [$type] = $this->subject($type, $id);
        $body = trim((string) $this->request->getPost('body'));
        if ($body === '') {
            return redirect()->back()->with('error', 'Write something in the note.');
        }
        $u = Auth::user();
        (new NoteModel())->insert([
            'subject_type' => $type, 'subject_id' => $id,
            'noted_on'     => $this->date((string) $this->request->getPost('noted_on')),
            'noted_time'   => $this->time((string) $this->request->getPost('noted_time')),
            'category'     => $this->category((string) $this->request->getPost('category')),
            'body'         => $body,
            'user_id'      => $u['id'],
            'author_name'  => $u['fullname'],
        ]);

        return redirect()->to($this->request->getPost('return') ?: $this->backUrl($type, $id))->with('success', 'Note added.');
    }

    public function update(string $type, int $id, int $noteId)
    {
        [$type] = $this->subject($type, $id);
        $m    = new NoteModel();
        $note = $this->mustOwn($m, $type, $id, $noteId);
        $body = trim((string) $this->request->getPost('body'));
        if ($body === '') {
            return redirect()->back()->with('error', 'Write something in the note.');
        }
        $m->update($note['id'], [
            'noted_on'   => $this->date((string) $this->request->getPost('noted_on')),
            'noted_time' => $this->time((string) $this->request->getPost('noted_time')),
            'category'   => $this->category((string) $this->request->getPost('category')),
            'body'       => $body,
            'edited_at'  => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to($this->request->getPost('return') ?: $this->backUrl($type, $id))->with('success', 'Note updated.');
    }

    public function delete(string $type, int $id, int $noteId)
    {
        [$type] = $this->subject($type, $id);
        $m = new NoteModel();
        $this->mustOwn($m, $type, $id, $noteId);
        $m->delete($noteId);

        return redirect()->to($this->request->getPost('return') ?: $this->backUrl($type, $id))->with('success', 'Note deleted.');
    }

    // ---- helpers ----

    /** Validate the subject type, check the area, load the record. Returns [type, row, display label]. */
    private function subject(string $type, int $id): array
    {
        $area = NoteModel::SUBJECTS[$type] ?? null;
        if (! $area) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (! Auth::can($area)) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ($type === 'student') {
            $row = (new StudentModel())->find($id);
            $label = $row ? student_name($row) : '';
        } elseif ($type === 'volunteer') {
            $row = (new VolunteerModel())->find($id);
            $label = $row ? trim($row['first_name'] . ' ' . $row['last_name']) : '';
        } elseif ($type === 'course') {
            $row = (new \App\Models\CourseModel())->find($id);
            $label = $row['name'] ?? '';
        } else {
            $row = (new HostHomeModel())->find($id);
            $label = $row['family_name'] ?? '';
        }
        if (! $row) {
            throw PageNotFoundException::forPageNotFound();
        }

        return [$type, $row, $label];
    }

    private function mustOwn(NoteModel $m, string $type, int $id, int $noteId): array
    {
        $note = $m->where('subject_type', $type)->where('subject_id', $id)->find($noteId);
        if (! $note) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ((int) $note['user_id'] !== Auth::id() && ! Auth::isSuper()) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $note;
    }

    private function backUrl(string $type, int $id): string
    {
        return site_url('notes/' . $type . '/' . $id);
    }

    private function date(string $v): string
    {
        $t = $v !== '' ? strtotime($v) : false;

        return $t ? date('Y-m-d', $t) : date('Y-m-d');
    }

    private function time(string $v): ?string
    {
        return preg_match('/^(\d{1,2}):(\d{2})/', trim($v), $m) ? sprintf('%02d:%s', (int) $m[1], $m[2]) : null;
    }

    private function category(string $v): ?string
    {
        return isset(NoteModel::CATEGORIES[$v]) ? $v : null;
    }
}
