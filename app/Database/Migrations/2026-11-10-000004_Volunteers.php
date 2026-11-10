<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Volunteers become the single record for every adult who serves. Mentors and host-home
 * families, previously their own copies, are converted in place:
 *   - each row of `mentors` becomes a volunteer with a Guys/Girls Mentor role; students.mentor_id
 *     is repointed to the volunteer id; the `mentors` table is dropped.
 *   - each host home's family name ("Scott & Pam Bailey") becomes one or two volunteers with the
 *     Host Family role, linked through host_home_people; the home's own email/phone columns are dropped.
 */
class Volunteers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'first_name'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'last_name'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'               => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'phone'               => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'address'             => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'city_state_zip'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'gender'              => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'status'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'member_of_dbc'       => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'tshirt_size'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'mp_group_placed_on'  => ['type' => 'DATE', 'null' => true],
            'opportunity_response'=> ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'driver_cleared_year' => ['type' => 'INT', 'null' => true],
            'background_check'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'saat_training'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'notes'               => ['type' => 'TEXT', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('email');
        $this->forge->addKey('last_name');
        $this->forge->createTable('volunteers');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'volunteer_id' => ['type' => 'INT'],
            'role'         => ['type' => 'VARCHAR', 'constraint' => 60],
            'category'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true], // life-skills category
            'is_lead'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('volunteer_id');
        $this->forge->createTable('volunteer_roles');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'host_home_id' => ['type' => 'INT'],
            'volunteer_id' => ['type' => 'INT'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('host_home_id');
        $this->forge->createTable('host_home_people');

        $db  = $this->db;
        $now = date('Y-m-d H:i:s');

        // --- mentors -> volunteers ---
        $map = [];
        if ($db->tableExists('mentors')) {
            foreach ($db->table('mentors')->get()->getResultArray() as $m) {
                [$first, $last] = $this->splitName($m['name']);
                $vid = $this->findOrCreate($first, $last, $m['email'] ?? null, $m['phone'] ?? null, $m['gender'] ?? null, $now);
                $role = ($m['gender'] ?? '') === 'F' ? 'Girls Mentor' : 'Guys Mentor';
                $this->addRole($vid, $role);
                $map[$m['id']] = $vid;
            }
            foreach ($map as $old => $new) {
                $db->table('students')->where('mentor_id', $old)->update(['mentor_id' => $new]);
            }
            $this->forge->dropTable('mentors', true);
        }

        // --- host home family names -> volunteers ---
        foreach ($db->table('host_homes')->get()->getResultArray() as $h) {
            $people = $this->splitFamily($h['family_name']);
            $emails = array_values(array_filter([$h['email'] ?? null, $h['email2'] ?? null]));
            $phones = array_values(array_filter([$h['phone'] ?? null, $h['phone2'] ?? null]));
            foreach ($people as $i => [$first, $last]) {
                $vid = $this->findOrCreate($first, $last, $emails[$i] ?? null, $phones[$i] ?? null, null, $now, $h['address'] ?? null);
                $this->addRole($vid, 'Host Family');
                $db->table('host_home_people')->insert(['host_home_id' => $h['id'], 'volunteer_id' => $vid]);
            }
        }
        foreach (['email', 'email2', 'phone', 'phone2'] as $col) {
            if ($db->fieldExists($col, 'host_homes')) {
                $this->forge->dropColumn('host_homes', $col);
            }
        }
    }

    public function down()
    {
        // Irreversible data migration; recreate the empty tables only.
        $this->forge->dropTable('host_home_people', true);
        $this->forge->dropTable('volunteer_roles', true);
        $this->forge->dropTable('volunteers', true);
    }

    private function splitName(string $name): array
    {
        $name  = trim($name);
        $parts = preg_split('/\s+/', $name);
        if (count($parts) === 1) {
            return [$name, ''];
        }
        $last = array_pop($parts);
        // keep suffixes with the last name: "Evan Midgette Sr."
        if (in_array(strtolower(rtrim($last, '.')), ['sr', 'jr', 'ii', 'iii'], true) && count($parts) > 1) {
            $last = array_pop($parts) . ' ' . $last;
        }

        return [implode(' ', $parts), $last];
    }

    /** "Scott & Pam Bailey" -> [[Scott, Bailey], [Pam, Bailey]]; "Doug Koeppen" -> [[Doug, Koeppen]] */
    private function splitFamily(string $family): array
    {
        $parts = array_map('trim', preg_split('/\s*(?:&|\band\b)\s*/i', $family));
        $parts = array_values(array_filter($parts));
        $out   = [];
        $lastKnown = '';
        foreach (array_reverse($parts) as $p) {
            [$f, $l] = $this->splitName($p);
            if ($l === '') {
                $l = $lastKnown;
            } else {
                $lastKnown = $l;
            }
            $out[] = [$f, $l];
        }

        return array_reverse($out);
    }

    private function findOrCreate(string $first, string $last, ?string $email, ?string $phone, ?string $gender, string $now, ?string $address = null): int
    {
        $t = $this->db->table('volunteers');
        $row = null;
        if ($email) {
            $row = $t->where('LOWER(email)', strtolower($email))->get()->getRowArray();
        }
        if (! $row) {
            $row = $this->db->table('volunteers')->where('LOWER(first_name)', strtolower($first))->where('LOWER(last_name)', strtolower($last))->get()->getRowArray();
        }
        if ($row) {
            $upd = [];
            if (! $row['email'] && $email) {
                $upd['email'] = $email;
            }
            if (! $row['phone'] && $phone) {
                $upd['phone'] = $phone;
            }
            if (! $row['gender'] && $gender) {
                $upd['gender'] = $gender;
            }
            if ($upd) {
                $this->db->table('volunteers')->where('id', $row['id'])->update($upd);
            }

            return (int) $row['id'];
        }
        $this->db->table('volunteers')->insert([
            'first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $phone, 'gender' => $gender,
            'address' => $address, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function addRole(int $vid, string $role): void
    {
        $t = $this->db->table('volunteer_roles');
        if (! $t->where('volunteer_id', $vid)->where('role', $role)->countAllResults()) {
            $this->db->table('volunteer_roles')->insert(['volunteer_id' => $vid, 'role' => $role]);
        }
    }
}
