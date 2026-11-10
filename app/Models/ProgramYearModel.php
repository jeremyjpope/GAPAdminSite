<?php

namespace App\Models;

use CodeIgniter\Model;

class ProgramYearModel extends Model
{
    protected $table         = 'program_years';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['label', 'year_number', 'start_date', 'end_date', 'moodle_overall_course_id', 'tuition_year_early', 'tuition_year_late', 'tuition_cutoff_date', 'program_description', 'transcript_notes', 'grade_scale', 'is_current'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
