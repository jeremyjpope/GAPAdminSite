<?php

namespace App\Commands;

use App\Models\VolunteerModel;
use App\Models\VolunteerRoleModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark gap:volunteer-role "First Last" "<from role>" "<to role>"
 * Replaces one role tag with another on one volunteer; with an empty from role the tag is added.
 */
class VolunteerRole extends BaseCommand
{
    protected $group       = 'GAP';
    protected $name        = 'gap:volunteer-role';
    protected $description = 'Move a volunteer from one role tag to another';
    protected $usage       = 'gap:volunteer-role "First Last" "From role" "To role"';

    public function run(array $params)
    {
        [$name, $from, $to] = [$params[0] ?? '', $params[1] ?? '', $params[2] ?? ''];
        if ($name === '' || $to === '') {
            CLI::error($this->usage);

            return;
        }
        $parts = explode(' ', trim($name), 2);
        $v = (new VolunteerModel())->where('LOWER(first_name)', strtolower($parts[0]))->where('LOWER(last_name)', strtolower($parts[1] ?? ''))->first();
        if (! $v) {
            CLI::error("No volunteer named {$name}");

            return;
        }
        $rm  = new VolunteerRoleModel();
        $row = $from !== '' ? $rm->where('volunteer_id', $v['id'])->where('role', $from)->first() : null;
        if ($row) {
            $rm->update($row['id'], ['role' => $to]);
            CLI::write("{$name}: {$from} -> {$to}", 'green');
        } elseif ($from === '') {
            $rm->ensure($v['id'], $to);
            CLI::write("{$name}: added {$to}", 'green');
        } else {
            CLI::error("{$name} does not have the role {$from}");
        }
    }
}
