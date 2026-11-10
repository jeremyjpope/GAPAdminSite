<?php

namespace App\Models;

use CodeIgniter\Model;

class HostHomePersonModel extends Model
{
    protected $table         = 'host_home_people';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['host_home_id', 'volunteer_id'];
    protected $useTimestamps = false;

    /** [host_home_id => [volunteer rows]] */
    public function peopleFor(array $homeIds): array
    {
        if (! $homeIds) {
            return [];
        }
        $links = $this->whereIn('host_home_id', $homeIds)->findAll();
        $vids  = array_column($links, 'volunteer_id');
        $vols  = $vids ? array_column((new VolunteerModel())->whereIn('id', $vids)->findAll(), null, 'id') : [];
        $out   = [];
        foreach ($links as $l) {
            if (isset($vols[$l['volunteer_id']])) {
                $out[$l['host_home_id']][] = $vols[$l['volunteer_id']];
            }
        }

        return $out;
    }
}
