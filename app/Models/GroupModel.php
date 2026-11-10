<?php

namespace App\Models;

use CodeIgniter\Model;

class GroupModel extends Model
{
    protected $table         = 'groups';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['group_set_id', 'name', 'sort_order', 'notes'];
    protected $useTimestamps = false;
}
