# GAP Admin Site — working notes for Claude

Read README.md first for what the site does, the stack and the data model. This file holds the
operating facts and conventions that are not obvious from the code.

## What this is
Private admin site for GAP (God's Alternative Program, Denton Bible Church), a nine-month gap-year
program with ~18 students per year. It complements the Moodle site at https://moodle.gapdbc.org and
replaces a yearly Excel workbook. Jeremy Pope is the director and the only developer. Proprietary,
all rights reserved (see LICENSE). Keep personal data (addresses, VINs, medical notes, interview
scores, the Excel file, import JSON, photos, SQLite db) out of git.

## Stack and conventions
- PHP 8.4, CodeIgniter 4.7, Bootstrap 5 via CDN, server-rendered views, dompdf for PDFs.
- Controllers return `view()`; forms POST with `csrf_field()`; flash messages via
  `redirect()->with('success'|'error', ...)` rendered by `flash_alerts()` in the layout.
- Access control: `AuthFilter` (logged in) and `AreaFilter` (`['filter' => 'area:<slug>']`) in
  `app/Config/Routes.php`. Areas are listed in `app/Config/Areas.php`; super admins pass everything.
  When adding a feature, add its area there, guard its route group, and add a sidebar entry in
  `app/Views/layouts/main.php` (the nav array).
- Program-year scoping: almost every table has `program_year_id`; use `current_year()` (helper) for
  the year selected in the sidebar. Students are one row per student per year.
- Helpers in `app/Helpers/gap_helper.php`: `current_year()`, `all_years()`, `student_name()`,
  `money()`, `fmt_date()`, `letter_for()`, `points_for()`, `flash_alerts()`.
- Models are thin `CodeIgniter\Model` subclasses with `allowedFields`; add new columns there too.
- Schema changes go in a new migration in `app/Database/Migrations/` (never edit the old ones);
  they must work on both SQLite (local) and MySQL (production).
- Volunteers are the single record for adults. Mentors = volunteers with a role in
  `Config\Volunteers::$mentorRoles`; `students.mentor_id` is a volunteer id. Host homes link people via
  `host_home_people`. Role tags live in `Config\Volunteers::$roles`; add new roles there, not in code.
  The `mentors` table no longer exists (migration 000004 converted it in place).
- Notes (`app/Controllers/Notes.php`, `app/Views/notes/_panel.php`) are polymorphic; to attach notes to a
  new kind of record add it to `NoteModel::SUBJECTS` (subject => required area) and the `subject()`
  loader, then include the panel view with `type`, `subjectId`, `notes`, `returnUrl`.
- Moodle calls go through `app/Libraries/MoodleClient.php` only. The server-side token belongs
  to the *Moodle mobile web service*, which does NOT include cohort functions; prefer
  `core_enrol_get_enrolled_users`, `core_user_get_users_by_field`, `core_course_get_courses`,
  `gradereport_user_get_grade_items`, `gradereport_overview_get_course_grades`.
- Moodle facts (2026-27): overall-standing course id 10 rolls up each class via mod_subcourse;
  class course ids: 2 Admin, 3 Womanhood, 4 Manhood, 9 Leadership, 16 Bible Exposition,
  46 Vocational, 47 Worldview, 49 Life Skills, 50 Special Topics, 51 Bible Study Methods.
  Jeremy's Moodle username is `dbcadmin` (email jpope@dentonbible.org). Moodle grade letters
  use a 93/90/87 scale but the transcript uses the program year's own scale (A ≥ 90).

## Local development
- `php spark serve --port 8081` (8080 is taken on Jeremy's Mac); `.claude/launch.json` matches.
- Local DB is SQLite at `writable/gapadmin.db` (ignored). `php spark migrate`, `php spark db:seed InitialSeeder`.
- `/dev-login` logs in as the first super admin without Moodle (development environment only).
  Note the built-in server caches env vars: after editing `.env`, restart `spark serve`.
- Re-import the Excel data with `tools/excel_to_json.py` + `php spark gap:import` (see README);
  `php spark gap:year` creates a missing program year. The import is idempotent but re-applies sheet
  values to students and replaces group membership; a payment is a duplicate only when date, amount
  AND note match, so re-importing after the parser's note wording changed will double payments
  (happened once for 2026-27; dedupe on student+date+amount keeping the lowest id).
- All eight years 2019-20..2026-27 are imported on the live site (Nov 2026). The workbooks live in
  OneDrive "GAP Team - Documents/Yearly Databases/<year>/". Each year's layout differs; the parser is
  header/title driven and has a regression check: Years 6-8 output must not change when touching it.
- Verify pages by logging in via `/dev-login` and reading them; PDFs can be fetched with curl
  using the session cookie from `/dev-login`.

## Production
- Server: `ubuntu@jeremyjpope.com` (same box as Moodle), key `~/.ssh/gapdbc.pem` on Jeremy's Mac.
  Ubuntu 22.04, nginx 1.18, PHP 8.4 fpm (socket `/run/php/php8.4-fpm.sock`), MySQL 8.4.
- App at `/var/www/gapadmin`, site https://admin.gapdbc.org (certbot certificate, nginx file
  `/etc/nginx/sites-available/admin.gapdbc.org`). Database `gapadmin`, user `gapadmin`;
  credentials and the Moodle token are only in the server's `.env` (mode 640, ubuntu:www-data).
- MySQL root needs a password Claude must not read (Moodle's config holds it); ask Jeremy to run
  any statement that needs root. The `gapadmin` user has full rights on its own schema.
- Deploy: commit, `git push origin main`, then
  `GIT_SSH_COMMAND="ssh -i ~/.ssh/gapdbc.pem" git push ssh://ubuntu@jeremyjpope.com/var/www/gapadmin main`
  (the checkout accepts pushes via `receive.denyCurrentBranch=updateInstead`; if the push is
  refused, the server tree is dirty: `git reset --hard` there first). Then on the server run
  `composer install --no-dev` if composer.lock changed and `sudo -u www-data php spark migrate`
  if there are new migrations. Restart `php8.4-fpm` only when `.env` changed.
- Always run `spark` on the server as `www-data` (`sudo -u www-data php spark ...`) so files in
  `writable/` keep the right owner. Never run the Excel import on the server casually: it
  overwrites student fields that may have been edited in the site.
- A deploy key exists on the server (`~/.ssh/gapadmin_deploy.pub`) but is not yet added to GitHub,
  so the server has no `origin` remote; direct push is the deploy path for now.
- Only commit and deploy when Jeremy asks; he reviews each step. Keep README.md current when
  features, setup or deployment change.

## Roadmap / not built yet
Applicants and interview rubric (area slug `applicants` already exists), the Men Volunteer List tab
(a subset of the volunteer list, ignored on purpose),
importing prior years' transcripts, finer Moodle→transcript mapping (one Moodle course feeding
several transcript lines), gender-specific catalog lines (today unused lines simply don't print).
- App timezone is America/Chicago (app/Config/App.php); note timestamps and dates use it.
