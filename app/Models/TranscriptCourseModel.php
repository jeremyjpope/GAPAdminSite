<?php

namespace App\Models;

use CodeIgniter\Model;

class TranscriptCourseModel extends Model
{
    protected $table         = 'transcript_courses';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['program_year_id', 'pillar', 'name', 'description', 'term', 'hours', 'sort_order', 'moodle_course_id'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
