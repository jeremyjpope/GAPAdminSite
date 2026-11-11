<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The workbooks used one tag, "Leadership Team/Advisory", for both groups. Rename it to "Leadership Team";
 * move advisory members with `php spark gap:volunteer-role "First Last" "Leadership Team" "Advisory Team"`.
 */
class SplitLeadershipRole extends Migration
{
    public function up()
    {
        $this->db->table('volunteer_roles')->where('role', 'Leadership Team/Advisory')->update(['role' => 'Leadership Team']);
    }

    public function down()
    {
        $this->db->table('volunteer_roles')->whereIn('role', ['Leadership Team', 'Advisory Team'])->update(['role' => 'Leadership Team/Advisory']);
    }
}
