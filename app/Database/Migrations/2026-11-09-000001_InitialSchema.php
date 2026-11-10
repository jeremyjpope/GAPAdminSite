<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class InitialSchema extends Migration
{
    private function id(): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true];
    }

    private function str(int $len = 255, bool $null = true): array
    {
        return ['type' => 'VARCHAR', 'constraint' => $len, 'null' => $null];
    }

    private function text(): array
    {
        return ['type' => 'TEXT', 'null' => true];
    }

    private function int(bool $null = true, $default = null): array
    {
        $f = ['type' => 'INT', 'null' => $null];
        if ($default !== null) {
            $f['default'] = $default;
        }
        return $f;
    }

    private function bool(int $default = 0): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'default' => $default];
    }

    private function money(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true];
    }

    private function date(): array
    {
        return ['type' => 'DATE', 'null' => true];
    }

    private function datetime(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }

    public function up()
    {
        // --- Users who may log in to the admin site (allowlist) ---
        $this->forge->addField([
            'id'            => $this->id(),
            'moodle_userid' => $this->int(),
            'username'      => $this->str(100),
            'email'         => $this->str(190),
            'fullname'      => $this->str(190),
            'is_superadmin' => $this->bool(),
            'is_active'     => $this->bool(1),
            'last_login_at' => $this->datetime(),
            'created_at'    => $this->datetime(),
            'updated_at'    => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('moodle_userid');
        $this->forge->addKey('email');
        $this->forge->createTable('users');

        $this->forge->addField([
            'id'      => $this->id(),
            'user_id' => $this->int(false),
            'area'    => $this->str(40, false),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->createTable('user_areas');

        // --- Program years (Year 8 = 2026-27, etc.) ---
        $this->forge->addField([
            'id'                       => $this->id(),
            'label'                    => $this->str(20, false),   // "2026-27"
            'year_number'              => $this->int(),            // 8
            'start_date'               => $this->date(),
            'end_date'                 => $this->date(),
            'moodle_overall_course_id' => $this->int(),            // course that rolls up all classes
            'tuition_year_early'       => $this->money(),          // applied before cutoff
            'tuition_year_late'        => $this->money(),          // applied on/after cutoff
            'tuition_cutoff_date'      => $this->date(),
            'program_description'      => $this->str(190),         // "Nine-month, full-time discipleship program"
            'transcript_notes'         => $this->text(),           // "Also completed: ..."
            'grade_scale'              => $this->text(),           // JSON: [{letter:A,min:90,points:4.0},...]
            'is_current'               => $this->bool(),
            'created_at'               => $this->datetime(),
            'updated_at'               => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('program_years');

        // --- Host homes & mentors ---
        $this->forge->addField([
            'id'              => $this->id(),
            'program_year_id' => $this->int(),
            'family_name'     => $this->str(190, false),   // "Scott & Pam Bailey"
            'email'           => $this->str(190),
            'email2'          => $this->str(190),
            'phone'           => $this->str(60),
            'phone2'          => $this->str(60),
            'address'         => $this->text(),
            'notes'           => $this->text(),
            'created_at'      => $this->datetime(),
            'updated_at'      => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('program_year_id');
        $this->forge->createTable('host_homes');

        $this->forge->addField([
            'id'              => $this->id(),
            'program_year_id' => $this->int(),
            'name'            => $this->str(190, false),
            'email'           => $this->str(190),
            'phone'           => $this->str(60),
            'gender'          => $this->str(10),
            'moodle_userid'   => $this->int(),
            'notes'           => $this->text(),
            'created_at'      => $this->datetime(),
            'updated_at'      => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('program_year_id');
        $this->forge->createTable('mentors');

        // --- Students ---
        $this->forge->addField([
            'id'                   => $this->id(),
            'program_year_id'      => $this->int(false),
            'moodle_userid'        => $this->int(),
            'first_name'           => $this->str(100, false),
            'last_name'            => $this->str(100, false),
            'preferred_name'       => $this->str(100),
            'email'                => $this->str(190),
            'phone'                => $this->str(60),
            'gender'               => $this->str(10),
            'birthday'             => $this->date(),
            'address'              => $this->text(),
            'tshirt_size'          => $this->str(20),
            'placement_preference' => $this->str(60),    // Preschool / Elementary
            'allergies_medical'    => $this->text(),
            'cleared_to_drive'     => $this->str(60),
            'auto_insurance'       => $this->str(190),
            'housing_preference'   => $this->str(190),
            'host_home_id'         => $this->int(),
            'mentor_id'            => $this->int(),
            'photo_path'           => $this->str(255),
            'status'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'enrolled'], // enrolled|withdrawn|graduated
            'attended_from'        => $this->str(40),    // "August 2026"
            'attended_to'          => $this->str(40),    // "May 2027"
            'notes'                => $this->text(),
            'created_at'           => $this->datetime(),
            'updated_at'           => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('program_year_id');
        $this->forge->addKey('moodle_userid');
        $this->forge->addKey('email');
        $this->forge->createTable('students');

        $this->forge->addField([
            'id'         => $this->id(),
            'student_id' => $this->int(false),
            'relation'   => $this->str(40),     // Mother / Father / Guardian / Emergency
            'name'       => $this->str(190),
            'email'      => $this->str(190),
            'phone'      => $this->str(60),
            'notes'      => $this->text(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('student_id');
        $this->forge->createTable('student_contacts');

        $this->forge->addField([
            'id'          => $this->id(),
            'student_id'  => $this->int(false),
            'description' => $this->str(190),   // "2017 Honda Pilot"
            'vin'         => $this->str(40),
            'oil_type'    => $this->str(190),
            'oil_notes'   => $this->text(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('student_id');
        $this->forge->createTable('student_vehicles');

        // --- Tuition ---
        $this->forge->addField([
            'id'         => $this->id(),
            'student_id' => $this->int(false),
            'plan_type'  => $this->str(20),     // year | semester | month | other
            'total_due'  => $this->money(),
            'notes'      => $this->text(),      // "Ben to pay", "post-dated checks", "link sent 7/17"
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('student_id');
        $this->forge->createTable('tuition_accounts');

        $this->forge->addField([
            'id'          => $this->id(),
            'student_id'  => $this->int(false),
            'paid_on'     => $this->date(),
            'amount'      => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'method'      => $this->str(30),    // check | cash | online | other
            'reference'   => $this->str(100),   // check number, confirmation
            'note'        => $this->text(),
            'recorded_by' => $this->int(),
            'created_at'  => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('student_id');
        $this->forge->createTable('payments');

        // --- Transcripts ---
        $this->forge->addField([
            'id'               => $this->id(),
            'program_year_id'  => $this->int(false),
            'pillar'           => $this->str(60),    // "Pillar I: Bible"
            'name'             => $this->str(120, false),
            'description'      => $this->str(190),
            'term'             => $this->str(40),    // Full Year / Fall 2026 / Spring 2027
            'hours'            => $this->int(),
            'sort_order'       => $this->int(true, 0),
            'moodle_course_id' => $this->int(),      // optional: Moodle course whose final grade feeds this line
            'created_at'       => $this->datetime(),
            'updated_at'       => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('program_year_id');
        $this->forge->createTable('transcript_courses');

        $this->forge->addField([
            'id'                   => $this->id(),
            'student_id'           => $this->int(false),
            'transcript_course_id' => $this->int(false),
            'letter'               => $this->str(3),
            'percent'              => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true],
            'source'               => $this->str(10),  // manual | moodle
            'updated_at'           => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['student_id', 'transcript_course_id']);
        $this->forge->createTable('transcript_grades');
    }

    public function down()
    {
        foreach (['transcript_grades', 'transcript_courses', 'payments', 'tuition_accounts', 'student_vehicles',
                  'student_contacts', 'students', 'mentors', 'host_homes', 'program_years', 'user_areas', 'users'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
