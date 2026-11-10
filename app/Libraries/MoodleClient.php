<?php

namespace App\Libraries;

use Config\Moodle as MoodleConfig;
use RuntimeException;

/**
 * Thin client for the Moodle REST web services.
 *
 * Two kinds of calls:
 *  - login(): exchanges a user's Moodle username/password for a token (used only to verify identity)
 *  - everything else: uses the server-side token from config (an admin-level Moodle user)
 */
class MoodleClient
{
    private MoodleConfig $cfg;

    public function __construct(?MoodleConfig $cfg = null)
    {
        $this->cfg = $cfg ?? config('Moodle');
    }

    public function baseUrl(): string
    {
        return rtrim($this->cfg->url, '/');
    }

    public function hasServiceToken(): bool
    {
        return $this->cfg->wsToken !== '';
    }

    /**
     * Verify a username/password against Moodle. Returns ['token' => ..., 'userid' => ..., 'username' => ..., 'fullname' => ...]
     * or throws RuntimeException with a user-facing message.
     */
    public function login(string $username, string $password): array
    {
        $resp = $this->post($this->baseUrl() . '/login/token.php', [
            'username' => $username,
            'password' => $password,
            'service'  => $this->cfg->loginService,
        ]);

        if (isset($resp['error'])) {
            $code = $resp['errorcode'] ?? '';
            if ($code === 'invalidlogin') {
                throw new RuntimeException('Invalid Moodle username or password.');
            }
            throw new RuntimeException('Moodle login failed: ' . $resp['error']);
        }
        if (empty($resp['token'])) {
            throw new RuntimeException('Moodle did not return a token.');
        }

        $info = $this->call('core_webservice_get_site_info', [], $resp['token']);

        return [
            'token'    => $resp['token'],
            'userid'   => (int) ($info['userid'] ?? 0),
            'username' => (string) ($info['username'] ?? $username),
            'fullname' => (string) ($info['fullname'] ?? ''),
        ];
    }

    /** Call a web service function with the server-side token. */
    public function call(string $function, array $params = [], ?string $token = null): array
    {
        $token ??= $this->cfg->wsToken;
        if ($token === '') {
            throw new RuntimeException('No Moodle web service token configured (moodle.wsToken in .env).');
        }

        $url  = $this->baseUrl() . '/webservice/rest/server.php';
        $body = array_merge([
            'wstoken'            => $token,
            'wsfunction'         => $function,
            'moodlewsrestformat' => 'json',
        ], $this->flatten($params));

        $resp = $this->post($url, $body);

        if (isset($resp['exception'])) {
            throw new RuntimeException('Moodle error (' . ($resp['errorcode'] ?? '?') . '): ' . ($resp['message'] ?? 'unknown'));
        }

        return $resp;
    }

    // ---- Convenience wrappers -------------------------------------------------

    /** Email address of a Moodle user, via the service token. Returns '' when unavailable. */
    public function userEmail(int $moodleUserId): string
    {
        if (! $this->hasServiceToken()) {
            return '';
        }
        try {
            $r = $this->call('core_user_get_users_by_field', ['field' => 'id', 'values' => [$moodleUserId]]);

            return strtolower(trim((string) ($r[0]['email'] ?? '')));
        } catch (RuntimeException $e) {
            log_message('warning', 'Could not read Moodle email for user {id}: {m}', ['id' => $moodleUserId, 'm' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Students enrolled in a course (users holding the "student" role there).
     * Returns user arrays: id, username, email, firstname, lastname, fullname.
     * Works with the Moodle mobile web service, unlike the cohort functions.
     */
    public function enrolledStudents(int $courseId): array
    {
        $users = $this->call('core_enrol_get_enrolled_users', ['courseid' => $courseId]);
        $out   = [];
        foreach ($users as $u) {
            $isStudent = false;
            foreach ($u['roles'] ?? [] as $r) {
                if (($r['shortname'] ?? '') === 'student') {
                    $isStudent = true;
                    break;
                }
            }
            if ($isStudent) {
                $out[] = $u;
            }
        }

        return $out;
    }

    /**
     * Search Moodle accounts by name or email. The mobile service blocks the site-wide user search,
     * but core_enrol_search_users (the "enrol users" dialog of a course) searches every account and
     * is allowed, so it is scoped to a course the token can see. Returns id, username, email, fullname.
     */
    public function searchUsers(string $query, int $courseId, int $limit = 15): array
    {
        $rows = $this->call('core_enrol_search_users', ['courseid' => $courseId, 'search' => $query, 'searchanywhere' => true, 'page' => 0, 'perpage' => $limit]);
        $ids  = array_map(static fn ($u) => (int) $u['id'], $rows);
        if (! $ids) {
            return [];
        }
        // the search result carries no username/email; fetch them by id (allowed for the mobile service)
        $out = [];
        foreach ($this->call('core_user_get_users_by_field', ['field' => 'id', 'values' => $ids]) as $u) {
            $out[] = ['id' => (int) $u['id'], 'username' => $u['username'] ?? '', 'email' => $u['email'] ?? '', 'fullname' => $u['fullname'] ?? trim(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? ''))];
        }
        usort($out, static fn ($a, $b) => strcmp($a['fullname'], $b['fullname']));

        return $out;
    }

    /** Non-student people enrolled in a course (the leadership team on the overall-standing course), with their role names. */
    public function courseStaff(int $courseId): array
    {
        $out = [];
        foreach ($this->call('core_enrol_get_enrolled_users', ['courseid' => $courseId]) as $u) {
            $roles = array_unique(array_column($u['roles'] ?? [], 'shortname'));
            if ($roles && ! in_array('student', $roles, true)) {
                $out[] = ['id' => (int) $u['id'], 'username' => $u['username'] ?? '', 'email' => $u['email'] ?? '', 'fullname' => $u['fullname'], 'roles' => implode(', ', $roles)];
            }
        }

        return $out;
    }

    /** All courses (id, fullname, shortname, categoryid). */
    public function courses(): array
    {
        $out = [];
        foreach ($this->call('core_course_get_courses', []) as $c) {
            if ((int) $c['id'] === 1) {
                continue; // site course
            }
            $out[] = ['id' => (int) $c['id'], 'fullname' => $c['fullname'], 'shortname' => $c['shortname'], 'categoryid' => (int) $c['categoryid']];
        }
        usort($out, fn ($a, $b) => strcmp($a['fullname'], $b['fullname']));

        return $out;
    }

    /**
     * Grade items for one user in one course (the "user report").
     * Returns ['items' => [[itemname, itemtype, itemmodule, graderaw, gradeformatted, percentageformatted, ...], ...], 'courseid' => ...]
     */
    public function userGradeItems(int $courseId, int $moodleUserId): array
    {
        $r = $this->call('gradereport_user_get_grade_items', ['courseid' => $courseId, 'userid' => $moodleUserId]);
        $ug = $r['usergrades'][0] ?? null;

        return ['courseid' => $courseId, 'items' => $ug['gradeitems'] ?? [], 'raw' => $ug];
    }

    /** Course totals for a user across all courses they are enrolled in: [courseid => ['grade' => '95.00', 'rawgrade' => ...]]. */
    public function userCourseTotals(int $moodleUserId): array
    {
        $r   = $this->call('gradereport_overview_get_course_grades', ['userid' => $moodleUserId]);
        $out = [];
        foreach ($r['grades'] ?? [] as $g) {
            $out[(int) $g['courseid']] = ['grade' => $g['grade'], 'rawgrade' => $g['rawgrade'] ?? null];
        }

        return $out;
    }

    // ---- HTTP -----------------------------------------------------------------

    private function post(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->cfg->timeout,
            CURLOPT_USERAGENT      => 'GAPAdminSite/1.0',
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Could not reach Moodle: ' . $err);
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new RuntimeException("Unexpected response from Moodle (HTTP {$http}).");
        }

        return $data;
    }

    /** Flatten nested params into Moodle's name[0][key] form. */
    private function flatten(array $params, string $prefix = ''): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}[{$k}]";
            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }
}
