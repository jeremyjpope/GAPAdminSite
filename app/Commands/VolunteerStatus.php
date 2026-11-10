<?php

namespace App\Commands;

use App\Models\VolunteerModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark gap:volunteer-status writable/import/year8.json [--dry-run]
 *
 * Marks every volunteer named in the given year's import JSON (volunteer list, mentors and
 * host-home people) as active, and every other volunteer as inactive. Volunteers already
 * inactive are left alone. Use it after importing the current year's workbook so the
 * volunteer list reflects who is serving this year.
 */
class VolunteerStatus extends BaseCommand
{
    protected $group       = 'GAP';
    protected $name        = 'gap:volunteer-status';
    protected $description = 'Set volunteers in a year\'s import JSON active and all others inactive';
    protected $usage       = 'gap:volunteer-status <json path> [--dry-run]';

    public function run(array $params)
    {
        $path = $params[0] ?? '';
        if ($path && ! is_file($path) && is_file(ROOTPATH . $path)) {
            $path = ROOTPATH . $path;
        }
        if (! $path || ! is_file($path)) {
            CLI::error("File not found: {$path}");

            return;
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) {
            CLI::error('Could not parse JSON');

            return;
        }
        $dry = CLI::getOption('dry-run') !== null;

        $vm        = new VolunteerModel();
        $all       = $vm->findAll();
        $byName    = [];   // "first last" (lowercase) => id, for mentor and host-home names
        foreach ($all as $row) {
            $byName[strtolower($row['first_name'] . ' ' . $row['last_name'])] = $row['id'];
        }
        $activeIds = [];
        $unmatched = [];

        foreach ($data['volunteers'] ?? [] as $v) {
            $row = $vm->findByEmailOrName($v['email'] ?: null, $v['first_name'], $v['last_name']);
            $row ? $activeIds[$row['id']] = true : $unmatched[] = $v['first_name'] . ' ' . $v['last_name'];
        }
        $names = [];
        foreach ($data['mentors'] ?? [] as $m) {
            $names[$m['name']] = $m['email'] ?? null;
        }
        foreach ($data['host_homes'] ?? [] as $h) {
            foreach ($h['people'] ?? [] as $p) {
                $names[$p] ??= null;
            }
        }
        foreach ($names as $name => $email) {
            $row = $email ? $vm->where('LOWER(email)', strtolower(trim($email)))->first() : null;
            $id  = $row['id'] ?? $byName[strtolower(trim($name))] ?? null;
            $id ? $activeIds[$id] = true : $unmatched[] = $name;
        }

        $toActive   = [];
        $toInactive = [];
        foreach ($all as $row) {
            if (isset($activeIds[$row['id']])) {
                if ($row['status'] !== 'active') {
                    $toActive[] = $row;
                }
            } elseif ($row['status'] !== 'inactive') {
                $toInactive[] = $row;
            }
        }

        CLI::write(sprintf('%s: %d volunteers, %d mentor/host names in file; %d records matched, %d unmatched',
            $data['label'] ?? $path, count($data['volunteers'] ?? []), count($names), count($activeIds), count($unmatched)), 'yellow');
        foreach ($unmatched as $n) {
            CLI::write("  unmatched: {$n}", 'red');
        }
        CLI::write(sprintf('Will mark %d active and %d inactive (of %d volunteers).', count($toActive), count($toInactive), count($all)));
        foreach ($toActive as $r) {
            CLI::write("  -> active: {$r['first_name']} {$r['last_name']} (was {$r['status']})");
        }

        if ($dry) {
            CLI::write('Dry run; nothing changed.');

            return;
        }
        if ($toActive) {
            $vm->whereIn('id', array_column($toActive, 'id'))->set('status', 'active')->update();
        }
        if ($toInactive) {
            $vm->whereIn('id', array_column($toInactive, 'id'))->set('status', 'inactive')->update();
        }
        CLI::write('Done.', 'green');
    }
}
