<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Student groupings: a "set" (e.g. Team Presentation Groups, Accountability Partners)
 * contains groups, each with member students. A student is in at most one group per set.
 */
class Groups extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'program_year_id' => ['type' => 'INT'],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 120],
            'kind'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'group'], // group | partners
            'group_label'     => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Group'],  // "Team", "Group", "Pair", "Age group"
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'sort_order'      => ['type' => 'INT', 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('program_year_id');
        $this->forge->createTable('group_sets');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'group_set_id' => ['type' => 'INT'],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 80],
            'sort_order'   => ['type' => 'INT', 'default' => 0],
            'notes'        => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('group_set_id');
        $this->forge->createTable('groups');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'group_id'   => ['type' => 'INT'],
            'student_id' => ['type' => 'INT'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('group_id');
        $this->forge->addKey('student_id');
        $this->forge->createTable('group_members');
    }

    public function down()
    {
        foreach (['group_members', 'groups', 'group_sets'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
