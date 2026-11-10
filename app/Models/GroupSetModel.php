<?php

namespace App\Models;

use CodeIgniter\Model;

class GroupSetModel extends Model
{
    protected $table         = 'group_sets';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['program_year_id', 'name', 'kind', 'group_label', 'notes', 'sort_order'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
