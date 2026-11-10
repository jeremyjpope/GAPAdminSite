<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseStaffModel extends Model
{
    protected $table         = 'course_staff';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['course_id', 'volunteer_id', 'role', 'notes'];
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
}
