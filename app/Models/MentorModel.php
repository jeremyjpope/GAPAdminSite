<?php

namespace App\Models;

use CodeIgniter\Model;

class MentorModel extends Model
{
    protected $table         = 'mentors';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['program_year_id', 'name', 'email', 'phone', 'gender', 'moodle_userid', 'notes'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
