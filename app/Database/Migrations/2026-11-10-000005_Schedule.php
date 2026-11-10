<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Course catalog (cross-year) with staff and assets, and the per-year weekly schedule:
 * time slots, planning blocks, one-off entries, recurring patterns and per-session staff.
 */
class Schedule extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 150],
            'pillar'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'other'],
            'category'         => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],   // life-skills category
            'audience'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'coed'],
            'color'            => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'default_location' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'moodle_course_id' => ['type' => 'INT', 'null' => true],
            'active'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('name');
        $this->forge->createTable('courses');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'course_id'    => ['type' => 'INT'],
            'volunteer_id' => ['type' => 'INT'],
            'role'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'instructor'],
            'notes'        => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('course_id');
        $this->forge->addKey('volunteer_id');
        $this->forge->createTable('course_staff');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'course_id'  => ['type' => 'INT'],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 190],
            'url'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'file_path'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notes'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('course_id');
        $this->forge->createTable('course_assets');

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'program_year_id' => ['type' => 'INT'],
            'label'           => ['type' => 'VARCHAR', 'constraint' => 80],
            'audience'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'coed'],
            'start_time'      => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],   // HH:MM
            'end_time'        => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
            'sort_order'      => ['type' => 'INT', 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('program_year_id');
        $this->forge->createTable('schedule_slots');

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'program_year_id' => ['type' => 'INT'],
            'label'           => ['type' => 'VARCHAR', 'constraint' => 80],
            'start_date'      => ['type' => 'DATE'],
            'end_date'        => ['type' => 'DATE'],
            'color'           => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('program_year_id');
        $this->forge->createTable('schedule_blocks');

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'program_year_id' => ['type' => 'INT'],
            'course_id'       => ['type' => 'INT'],
            'slot_id'         => ['type' => 'INT', 'null' => true],
            'slot_span'       => ['type' => 'INT', 'default' => 1],
            'weekday'         => ['type' => 'INT'],                       // 1 = Monday .. 7 = Sunday
            'start_date'      => ['type' => 'DATE'],
            'end_date'        => ['type' => 'DATE'],
            'title'           => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'group_id'        => ['type' => 'INT', 'null' => true],
            'audience'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'location'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'lo_la'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('program_year_id');
        $this->forge->createTable('schedule_recurrences');

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'program_year_id' => ['type' => 'INT'],
            'course_id'       => ['type' => 'INT'],
            'slot_id'         => ['type' => 'INT', 'null' => true],         // null = all day
            'slot_span'       => ['type' => 'INT', 'default' => 1],
            'start_date'      => ['type' => 'DATE'],
            'end_date'        => ['type' => 'DATE'],
            'title'           => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'group_id'        => ['type' => 'INT', 'null' => true],
            'group_label'     => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],  // free text when not linked to a group
            'audience'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'location'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'lo_la'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'planned'],
            'comment'         => ['type' => 'TEXT', 'null' => true],
            'recurrence_id'   => ['type' => 'INT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['program_year_id', 'start_date']);
        $this->forge->addKey('course_id');
        $this->forge->createTable('schedule_entries');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'entry_id'     => ['type' => 'INT'],
            'volunteer_id' => ['type' => 'INT'],
            'role'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'instructor'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('entry_id');
        $this->forge->addKey('volunteer_id');
        $this->forge->createTable('entry_staff');

        $this->forge->addColumn('program_years', ['week_one_starts' => ['type' => 'DATE', 'null' => true, 'after' => 'end_date']]);
    }

    public function down()
    {
        foreach (['entry_staff', 'schedule_entries', 'schedule_recurrences', 'schedule_blocks', 'schedule_slots', 'course_assets', 'course_staff', 'courses'] as $t) {
            $this->forge->dropTable($t, true);
        }
        $this->forge->dropColumn('program_years', 'week_one_starts');
    }
}
