<?php

namespace App\Models;

use CodeIgniter\Model;

class GroupMemberModel extends Model
{
    protected $table         = 'group_members';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['group_id', 'student_id'];
    protected $useTimestamps = false;
}
