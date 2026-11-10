<?php

namespace App\Models;

use CodeIgniter\Model;

class VolunteerModel extends Model
{
    protected $table         = 'volunteers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city_state_zip', 'gender', 'status',
        'member_of_dbc', 'tshirt_size', 'mp_group_placed_on', 'opportunity_response', 'driver_cleared_year',
        'background_check', 'saat_training', 'notes'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    /** Volunteers holding any of the given roles, ordered by name. Includes extra ids (e.g. currently assigned mentors). */
    public function withRoles(array $roles, array $extraIds = []): array
    {
        $ids = array_column((new VolunteerRoleModel())->whereIn('role', $roles ?: [''])->findAll(), 'volunteer_id');
        $ids = array_unique(array_merge($ids, $extraIds));
        if (! $ids) {
            return [];
        }

        return $this->whereIn('id', $ids)->orderBy('last_name')->orderBy('first_name')->findAll();
    }

    /** [volunteer_id => [role rows]] for the given volunteers. */
    public function rolesFor(array $volunteerIds): array
    {
        if (! $volunteerIds) {
            return [];
        }
        $out = [];
        foreach ((new VolunteerRoleModel())->whereIn('volunteer_id', $volunteerIds)->orderBy('role')->findAll() as $r) {
            $out[$r['volunteer_id']][] = $r;
        }

        return $out;
    }

    public function findByEmailOrName(?string $email, string $first, string $last): ?array
    {
        if ($email) {
            $row = $this->where('LOWER(email)', strtolower(trim($email)))->first();
            if ($row) {
                return $row;
            }
        }

        return $this->where('LOWER(first_name)', strtolower(trim($first)))->where('LOWER(last_name)', strtolower(trim($last)))->first();
    }
}
