<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseModel extends Model
{
    protected $table         = 'courses';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'pillar', 'category', 'audience', 'color', 'default_location', 'description', 'moodle_course_id', 'active'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
