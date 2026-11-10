<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Link to the student's payment plan in Ministry Platform. */
class TuitionMpLink extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tuition_accounts', ['mp_link' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'notes']]);
    }

    public function down()
    {
        $this->forge->dropColumn('tuition_accounts', 'mp_link');
    }
}
