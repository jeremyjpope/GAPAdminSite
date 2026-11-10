<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Models\PaymentModel;
use App\Models\StudentModel;
use App\Models\TuitionAccountModel;

class Tuition extends BaseController
{
    public function index()
    {
        $year     = current_year();
        $students = (new StudentModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('last_name')->orderBy('first_name')->findAll();
        $ids      = array_column($students, 'id') ?: [0];
        $accts    = array_column((new TuitionAccountModel())->whereIn('student_id', $ids)->findAll(), null, 'student_id');
        $paid     = [];
        $today    = date('Y-m-d');
        $payments = (new PaymentModel())->whereIn('student_id', $ids)->orderBy('paid_on')->orderBy('id')->findAll();
        foreach ($payments as $p) {
            $row = &$paid[$p['student_id']];
            $row ??= ['paid' => 0.0, 'scheduled' => 0.0, 'last_paid' => null];
            if ($p['paid_on'] > $today) {
                $row['scheduled'] += (float) $p['amount'];
            } else {
                $row['paid'] += (float) $p['amount'];
                $row['last_paid'] = max($row['last_paid'] ?? '', $p['paid_on']);
            }
            unset($row);
        }

        // list or month-grid view; the choice is remembered in a cookie
        $view = $this->request->getGet('view');
        if (in_array($view, ['list', 'grid'], true)) {
            $this->response->setCookie('tuition_view', $view, 86400 * 365);
        } else {
            $view = $this->request->getCookie('tuition_view') === 'grid' ? 'grid' : 'list';
        }

        // months July of the first calendar year through April of the second, like the spreadsheet
        $months = [];
        if ($year && preg_match('/^(\d{4})-(\d{2})$/', $year['label'], $m)) {
            $y1 = (int) $m[1];
            for ($i = 0; $i < 10; $i++) {
                $months[] = date('Y-m', mktime(0, 0, 0, 7 + $i, 1, $y1));
            }
        }
        $byMonth = [];   // student_id => 'YYYY-MM' (or 'other') => payments
        foreach ($payments as $p) {
            $k = substr((string) $p['paid_on'], 0, 7);
            $byMonth[$p['student_id']][in_array($k, $months, true) ? $k : 'other'][] = $p;
        }

        return view('tuition/index', ['title' => 'Tuition', 'year' => $year, 'students' => $students, 'accts' => $accts, 'paid' => $paid,
            'view' => $view, 'months' => $months, 'byMonth' => $byMonth, 'today' => $today]);
    }

    public function student(int $id)
    {
        $s = (new StudentModel())->find($id);
        if (! $s) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $acct     = (new TuitionAccountModel())->where('student_id', $id)->first();
        $payments = (new PaymentModel())->where('student_id', $id)->orderBy('paid_on')->orderBy('id')->findAll();
        $today     = date('Y-m-d');
        $paid      = array_sum(array_map(fn ($p) => $p['paid_on'] <= $today ? (float) $p['amount'] : 0, $payments));
        $scheduled = array_sum(array_map(fn ($p) => $p['paid_on'] > $today ? (float) $p['amount'] : 0, $payments));

        return view('tuition/student', [
            'title' => 'Tuition: ' . student_name($s), 's' => $s, 'acct' => $acct, 'payments' => $payments,
            'paid' => $paid, 'scheduled' => $scheduled, 'year' => current_year(),
        ]);
    }

    public function saveAccount(int $id)
    {
        $m    = new TuitionAccountModel();
        $data = [
            'student_id' => $id,
            'plan_type'  => $this->request->getPost('plan_type'),
            'total_due'  => (float) str_replace([',', '$'], '', (string) $this->request->getPost('total_due')),
            'notes'      => trim((string) $this->request->getPost('notes')) ?: null,
            'mp_link'    => trim((string) $this->request->getPost('mp_link')) ?: null,
        ];
        if ($data['mp_link'] && ! preg_match('#^https?://#i', $data['mp_link'])) {
            $data['mp_link'] = 'https://' . $data['mp_link'];
        }
        $existing = $m->where('student_id', $id)->first();
        $existing ? $m->update($existing['id'], $data) : $m->insert($data);

        return redirect()->to('/tuition/' . $id)->with('success', 'Plan saved.');
    }

    public function addPayment(int $id)
    {
        $amount = (float) str_replace([',', '$'], '', (string) $this->request->getPost('amount'));
        if ($amount <= 0) {
            return redirect()->back()->with('error', 'Amount must be greater than zero.');
        }
        (new PaymentModel())->insert([
            'student_id'  => $id,
            'paid_on'     => $this->request->getPost('paid_on') ?: date('Y-m-d'),
            'amount'      => $amount,
            'method'      => $this->request->getPost('method'),
            'reference'   => trim((string) $this->request->getPost('reference')) ?: null,
            'note'        => trim((string) $this->request->getPost('note')) ?: null,
            'recorded_by' => Auth::id(),
        ]);

        return redirect()->to('/tuition/' . $id)->with('success', 'Payment recorded.');
    }

    public function deletePayment(int $id, int $pid)
    {
        (new PaymentModel())->where('student_id', $id)->delete($pid);

        return redirect()->to('/tuition/' . $id)->with('success', 'Payment deleted.');
    }
}
