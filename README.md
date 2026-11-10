# GAP Admin Site

Private administration site for **GAP (God's Alternative Program)** at Denton Bible Church.
It sits beside the program's Moodle site (<https://moodle.gapdbc.org>) and replaces the yearly
Excel "database" for everything Moodle does not do: the student directory, parents and host
homes, mentors, tuition tracking, and official transcripts.

Staff log in with their **Moodle username and password**. Only people listed under
*Site admin → Access* can get in, and each person is granted specific areas.

> Proprietary, all rights reserved. See [LICENSE](LICENSE). Private repository.

---

## What it does today

| Area | What you can do |
|---|---|
| **Dashboard** | Headcount, tuition collected vs. outstanding, students without a host home or mentor, recent payments. |
| **Students** | Directory for the current program year: contact details, parents/guardians, vehicle + VIN + oil info, medical notes, driving clearance, placement preference, photo upload, free-text notes. Link to the Moodle account and view the live Moodle gradebook. "Sync from Moodle" creates/links students from everyone enrolled as a student in the year's overall-standing course. |
| **Tuition** | Per-student plan (year / semester / monthly, total due) and a payment ledger (date, amount, method, check #, note). Balances at a glance; post-dated checks are shown separately from money already received. |
| **Host Homes** | Households for the year (name, address, notes) with the people who live there linked from Volunteers; assign students; see who lives where. |
| **Notes** | Dated (optionally timed) notes on a student or a host home: strikes, discipline, parent/host contact, housing, medical, praise. Each note is signed automatically with the logged-in user and the entry time; only the author or a super admin can edit or delete it. Student notes sit on the student page; host home notes open from the journal button on each home. |
| **Mentors** | Assign one mentor per student. A mentor is simply a volunteer with the Guys Mentor or Girls Mentor role. |
| **Volunteers** | One record per adult who serves (contact, DBC membership, Ministry Platform placement, driver clearance year, background check, SAAT, notes) with any number of role tags (Host Family, Life Skills + category and lead flag, Guys/Girls Mentor, Special Topic, …) and a status (active / inactive / potential). Mentors and host-home people are volunteers, so nobody is stored twice. Dated notes attach here too. |
| **Groups** | Any number of "group sets" per year (presentation teams, Children's Learning Center age groups, guys' and girls' Friday night groups, accountability partners…). Each set has named groups; a student is in at most one group per set. Printable roster per set. |
| **Transcripts** | A per-year **course catalog** (pillar, course, description, term, hours) that defines the lines on the transcript. Enter letter grades by hand or **pull course totals from Moodle** for lines mapped to a Moodle course. Generates the official transcript as a PDF (same layout as the 2022-23 transcripts). |
| **Site admin** | Who can log in and which areas they see; program years (dates, tuition rates, grading scale, Moodle overall-standing course); Moodle connection check. |

Planned (not built): applicants / interview rubric, volunteer lists, multi-year transcript history import.

## Stack

* PHP 8.4, [CodeIgniter 4.7](https://codeigniter.com), Bootstrap 5 (CDN), server-rendered views.
* MySQL in production (its own schema on the Moodle server); SQLite for local development.
* [dompdf](https://github.com/dompdf/dompdf) for transcript PDFs.
* Moodle REST web services for login verification, cohort/user lookup and grades. No direct access to Moodle's database.

## How Moodle is used

1. **Login** — the site posts the username/password to Moodle's `login/token.php` (service `moodle_mobile_app`, which is already enabled). If Moodle accepts it, the site calls `core_webservice_get_site_info` to learn the Moodle user id and name, then checks its own **allowlist** (`users` table). The password and token are not stored. Note: Moodle's MFA email factor only applies to browser logins, not token logins.
2. **Server-side token** — reading enrolments, user details and grades needs a token for an admin-level Moodle user, stored in `.env` as `moodle.wsToken`. Create it in Moodle under *Site administration → Server → Web services → Manage tokens* (service: *Moodle mobile web service*). *Site admin → Moodle* in this app shows whether it works. The mobile service does not expose cohort functions, which is why the student sync reads course enrolments rather than the `gap-all` cohort.
3. **Grades** — each program year points at a Moodle course (for 2026-27 it is *GAP Overall Standing 26-27*, course id 10). That course rolls every class's final grade up with the Subcourse plugin, so the student page can show the whole gradebook, and transcript lines mapped to a Moodle course can pull their final grade (`gradereport_overview_get_course_grades`) and convert it with the year's grading scale.

## Local development

```bash
composer install
cp env.example .env     # or keep the committed-free .env you already have
php spark migrate
php spark db:seed InitialSeeder   # creates the first super admin + 2026-27 year + transcript catalog
php spark serve --port 8081
```

The default local `.env` uses SQLite (`writable/gapadmin.db`), so no MySQL is needed on your laptop.
In the **development** environment the login page shows a *Dev shortcut* that logs you in as the
first super admin without contacting Moodle. It returns 404 in production.

### Importing a yearly Excel database

```bash
pip3 install openpyxl
php spark gap:year 2024-25 --number 6 --early 3500       # only if the program year does not exist yet
python3 tools/excel_to_json.py "/path/to/2024-25 Year 6 Database.xlsx" writable/import/year6.json 2024-25
php spark gap:import writable/import/year6.json --year 2024-25
php spark gap:volunteer-status writable/import/year8.json     # current year only: see below
```

Volunteer status is not year-scoped (a volunteer is one record across years), so after importing the
*current* year's workbook run `gap:volunteer-status` with that year's JSON: everyone it names (volunteer
list, mentors, host-home people) becomes *active* and every other volunteer becomes *inactive*.
Add `--dry-run` to preview. Do not run it with an old year's file.

The third argument to the script is the program-year label; it decides which calendar years undated
payments and "Fall"/"Spring" belong to. All eight workbooks from 2019-20 to 2026-27 have been imported;
the parser reads columns by header name and finds sections by title, so it copes with the layout
changing from year to year (roster tabs, Guys/Girls applicant tabs, group matrices, housing sheets
with one host per row, tuition written as free text or running totals). Review its WARNING lines:
they name every fuzzy name match, assumed tuition total and ledger that exceeds its total.

Sheets it understands (any may be missing): a roster tab (*Student Names & Email Addresses*, a
*… Groups* tab, *2019 Students*, *GAP Guy Directory*), applicant tabs (*Applicants*, *Guys*/*Girls*,
*2023-24 GUY*…), *Student Auto Information*, *Tuition Info*, *Host Home Assignments* / *Host Home* /
*Host Homes* / *Host Home Information*, *Mentor Information* (either layout), *Groups Teams* /
*GroupsTeams*, *Volunteer List* / *GAP Volunteer List* / *GAP Master Volunteer List*, *GAP 360 for Women
Invitees* (compliance fields for people already on the list) and *Volunteer Teams* (life-skills leads).
Volunteers are matched by email, then by name, across years; the import only fills blank fields on an
existing volunteer, so one person hosting in 2019 and mentoring in 2026 is a single record. It prints warnings for
anything it could not interpret (for example a monthly plan marked PAID with no amounts in the
month grid). Payment cells like `ck #1094 $400/parents' acct; dated 9/15/26` become ledger rows
with the check number and date; the full cell text is kept in the note. The import is idempotent:
re-running it updates students and only adds contacts, vehicles and payments that are not already there.
`writable/import/` is git-ignored because the file contains personal data.

## Production deployment (Ubuntu, nginx, php-fpm, MySQL)

1. Create a database and user, e.g. `gapadmin`, and put the credentials in `.env` (start from `env.example`, set `CI_ENVIRONMENT = production`, `app.baseURL`, `database.*`, `moodle.wsToken`).
2. `composer install --no-dev --optimize-autoloader`, then `php spark migrate` and `php spark db:seed InitialSeeder`.
3. Make `writable/` writable by the php-fpm user. Student photos are stored in `writable/uploads/photos` and are served through the app (never directly by nginx).
4. nginx server block with the document root at `public/` (standard CodeIgniter 4 setup):

```nginx
server {
    server_name admin.gapdbc.org;
    root /var/www/gapadmin/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php$is_args$args; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~ /\. { deny all; }
}
```

5. Add a TLS certificate (certbot) and log in with your Moodle account. The seeder creates the super admin by email (`jpope@dentonbible.org`); the first successful login stores the Moodle user id.

### Deploying updates

The live site is `/var/www/gapadmin` on the Moodle server (`ubuntu@jeremyjpope.com`), served by the
`admin.gapdbc.org` nginx site. The checkout there receives pushes directly
(`receive.denyCurrentBranch = updateInstead`), so from a laptop with the server key:

```bash
git push ssh://ubuntu@jeremyjpope.com/var/www/gapadmin main
```

Then on the server, when `composer.json` or the migrations changed:

```bash
cd /var/www/gapadmin && composer install --no-dev --optimize-autoloader && sudo -u www-data php spark migrate
```

Alternatively add the server's deploy key (`~/.ssh/gapadmin_deploy.pub` on the server) to the GitHub
repo, add `origin` there, and `git pull` instead. `writable/` is owned by `www-data`; run `spark`
commands on the server with `sudo -u www-data` so log, session and upload files keep that owner.

## Project layout

```
app/Config/Areas.php            access areas (students, tuition, housing, mentors, volunteers, groups, transcripts, applicants, admin)
app/Config/Volunteers.php       role tags, mentor roles, life-skills categories, statuses
app/Config/Moodle.php           Moodle settings (overridden from .env)
app/Libraries/MoodleClient.php  REST client (login, cohorts, users, grades)
app/Libraries/Auth.php          session helper + area checks
app/Filters/                    auth / area route filters
app/Controllers/                Auth, Dashboard, Students, Tuition, HostHomes, Mentors, Volunteers, Groups, Notes, Transcripts, Admin
app/Views/                      Bootstrap views; transcripts/pdf.php is the PDF template
app/Database/Migrations/        schema (one initial migration)
app/Database/Seeds/             InitialSeeder (super admin, 2026-27, transcript catalog)
app/Commands/                   gap:import, gap:year, gap:volunteer-status (spark commands)
app/Helpers/gap_helper.php      current_year(), money(), letter_for(), ...
tools/excel_to_json.py          Excel → JSON for the importer
public/img/gap-logo.png         logo used on the transcript
```

## Data model (short version)

`program_years` → `students` (one row per student per year) → `student_contacts`, `student_vehicles`,
`tuition_accounts` (plan + total due), `payments`, `transcript_grades`.
`volunteers` (one per adult, not per year) → `volunteer_roles` (role, life-skills category, is_lead).
`students.mentor_id` points at a volunteer. `host_homes` belong to a program year and link their people
through `host_home_people` → `volunteers`. `transcript_courses` is the per-year catalog.
`group_sets` → `groups` → `group_members` hold the team / Friday night / accountability groupings.
`notes` is polymorphic (`subject_type` student|host_home + `subject_id`), with `noted_on` (date), optional
`noted_time`, `category`, `user_id` and an `author_name` snapshot.
`users` + `user_areas` is the login allowlist. All Moodle references are plain integer ids
(`moodle_userid`, `moodle_course_id`, `moodle_overall_course_id`).
