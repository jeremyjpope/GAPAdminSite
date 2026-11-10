<?php

namespace App\Models;

use CodeIgniter\Model;

class HostHomeModel extends Model
{
    protected $table         = 'host_homes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['program_year_id', 'family_name', 'email', 'email2', 'phone', 'phone2', 'address', 'notes'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
