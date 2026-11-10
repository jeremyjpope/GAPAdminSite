<?php

namespace App\Controllers;

use App\Libraries\Auth as AuthLib;
use App\Libraries\MoodleClient;
use App\Models\UserAreaModel;
use App\Models\UserModel;
use RuntimeException;

class Auth extends BaseController
{
    public function login()
    {
        if (AuthLib::check()) {
            return redirect()->to('/');
        }

        return view('auth/login');
    }

    public function attempt()
    {
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        if ($username === '' || $password === '') {
            return redirect()->back()->with('error', 'Enter your Moodle username and password.');
        }

        try {
            $m = (new MoodleClient())->login($username, $password);
        } catch (RuntimeException $e) {
            log_message('warning', 'Moodle login failed for {u}: {m}', ['u' => $username, 'm' => $e->getMessage()]);

            return redirect()->back()->with('error', $e->getMessage());
        }

        // Match against the allowlist: by Moodle user id first, then by username, then by email.
        $users = new UserModel();
        $row   = $users->where('moodle_userid', $m['userid'])->first()
              ?? $users->where('LOWER(username)', strtolower($m['username']))->first()
              ?? $users->where('LOWER(email)', strtolower($username))->first();

        if (! $row || ! $row['is_active']) {
            log_message('warning', 'Login denied (not allowlisted): {u}', ['u' => $m['username']]);

            return redirect()->back()->with('error', 'Your Moodle login is valid, but this account has not been given access to the admin site.');
        }

        // Keep the allowlist row in sync with Moodle.
        $users->update($row['id'], [
            'moodle_userid' => $m['userid'],
            'username'      => $m['username'],
            'fullname'      => $m['fullname'] ?: $row['fullname'],
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
        $row   = $users->find($row['id']);
        $areas = array_column((new UserAreaModel())->where('user_id', $row['id'])->findAll(), 'area');

        AuthLib::login($row, $areas);
        $to = session()->get('intended') ?: '/';
        session()->remove('intended');

        return redirect()->to($to);
    }

    /**
     * Development-only shortcut: logs in the first active super admin without talking to Moodle.
     * Hard-disabled unless CI_ENVIRONMENT is "development".
     */
    public function devLogin()
    {
        if (ENVIRONMENT !== 'development') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $row = (new UserModel())->where('is_superadmin', 1)->where('is_active', 1)->first();
        if (! $row) {
            return redirect()->to('/login')->with('error', 'No super admin exists yet. Run: php spark db:seed InitialSeeder');
        }
        AuthLib::login($row, []);

        return redirect()->to('/')->with('info', 'Logged in via the development shortcut (not available in production).');
    }

    public function logout()
    {
        AuthLib::logout();

        return redirect()->to('/login')->with('success', 'You have been logged out.');
    }
}
