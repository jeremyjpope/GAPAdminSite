<?php

namespace App\Models;

use CodeIgniter\Model;

class EntryStaffModel extends Model
{
    protected $table         = 'entry_staff';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['entry_id', 'volunteer_id', 'role'];
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
}
