<?php

namespace App\Models;

use CodeIgniter\Model;

class UserAreaModel extends Model
{
    protected $table         = 'user_areas';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'area'];
    protected $useTimestamps = false;
}
