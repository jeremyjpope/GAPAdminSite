<?php

namespace App\Models;

use CodeIgniter\Model;

class TranscriptGradeModel extends Model
{
    protected $table         = 'transcript_grades';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['student_id', 'transcript_course_id', 'letter', 'percent', 'source', 'updated_at'];
    protected $useTimestamps = false;
}
