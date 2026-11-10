<?php

namespace App\Models;

use CodeIgniter\Model;

class VolunteerRoleModel extends Model
{
    protected $table         = 'volunteer_roles';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['volunteer_id', 'role', 'category', 'is_lead'];
    protected $useTimestamps = false;

    public function ensure(int $volunteerId, string $role, ?string $category = null, ?bool $lead = null): void
    {
        $row = $this->where('volunteer_id', $volunteerId)->where('role', $role)->first();
        if ($row) {
            $upd = [];
            if ($category !== null && ! $row['category']) {
                $upd['category'] = $category;
            }
            if ($lead !== null) {
                $upd['is_lead'] = (int) $lead;
            }
            if ($upd) {
                $this->update($row['id'], $upd);
            }

            return;
        }
        $this->insert(['volunteer_id' => $volunteerId, 'role' => $role, 'category' => $category, 'is_lead' => (int) ($lead ?? false)]);
    }
}
