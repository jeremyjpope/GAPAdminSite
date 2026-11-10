<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Admissions: applicants per year, their checklist steps and interview rubric scores. */
class Applicants extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'program_year_id'      => ['type' => 'INT'],
            'submission_order'     => ['type' => 'INT', 'null' => true],
            'first_name'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'last_name'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'preferred_name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'gender'               => ['type' => 'VARCHAR', 'constraint' => 1, 'null' => true],
            'email'                => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'phone'                => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'address'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'birthday'             => ['type' => 'DATE', 'null' => true],
            'mom_email'            => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'dad_email'            => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'tshirt_size'          => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'placement_preference' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'allergies_medical'    => ['type' => 'TEXT', 'null' => true],
            'cleared_to_drive'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'auto_insurance'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'housing_preference'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'tuition_note'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'mp_link'              => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'notes'                => ['type' => 'TEXT', 'null' => true],
            'status'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'applied'],
            'student_id'           => ['type' => 'INT', 'null' => true],
            'prior_applicant_id'   => ['type' => 'INT', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('program_year_id');
        $this->forge->addKey('last_name');
        $this->forge->createTable('applicants');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'applicant_id' => ['type' => 'INT'],
            'step'         => ['type' => 'VARCHAR', 'constraint' => 30],
            'done'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'done_on'      => ['type' => 'DATE', 'null' => true],
            'note'         => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('applicant_id');
        $this->forge->createTable('applicant_steps');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'applicant_id' => ['type' => 'INT'],
            'user_id'      => ['type' => 'INT', 'null' => true],
            'scorer_name'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],   // imported scores without a site user
            'category'     => ['type' => 'VARCHAR', 'constraint' => 20],
            'score'        => ['type' => 'INT'],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['applicant_id', 'user_id']);
        $this->forge->createTable('applicant_scores');
    }

    public function down()
    {
        foreach (['applicant_scores', 'applicant_steps', 'applicants'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
