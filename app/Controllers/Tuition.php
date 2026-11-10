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
        foreach ((new PaymentModel())->whereIn('student_id', $ids)->findAll() as $p) {
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

        return view('tuition/index', ['title' => 'Tuition', 'year' => $year, 'students' => $students, 'accts' => $accts, 'paid' => $paid]);
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
        ];
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
