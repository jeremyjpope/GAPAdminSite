<?php

namespace App\Models;

use CodeIgniter\Model;

class TuitionAccountModel extends Model
{
    protected $table         = 'tuition_accounts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['student_id', 'plan_type', 'total_due', 'notes'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
