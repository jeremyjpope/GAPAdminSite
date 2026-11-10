<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentContactModel extends Model
{
    protected $table         = 'student_contacts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['student_id', 'relation', 'name', 'email', 'phone', 'notes'];
    protected $useTimestamps = false;
}
