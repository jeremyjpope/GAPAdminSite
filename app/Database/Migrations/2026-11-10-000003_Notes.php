<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Dated, signed notes attached to a student or a host home (interaction log, strikes, etc.).
 */
class Notes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'subject_type' => ['type' => 'VARCHAR', 'constraint' => 20],            // student | host_home
            'subject_id'   => ['type' => 'INT'],
            'noted_on'     => ['type' => 'DATE'],                                    // always set
            'noted_time'   => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true], // "HH:MM" when known
            'category'     => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], // general | strike | discipline | contact | housing | medical | praise
            'body'         => ['type' => 'TEXT'],
            'user_id'      => ['type' => 'INT'],                                     // author (users.id)
            'author_name'  => ['type' => 'VARCHAR', 'constraint' => 190],            // snapshot at time of writing
            'edited_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['subject_type', 'subject_id']);
        $this->forge->createTable('notes');
    }

    public function down()
    {
        $this->forge->dropTable('notes', true);
    }
}
