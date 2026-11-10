<?php

namespace App\Models;

use CodeIgniter\Model;

class ScheduleRecurrenceModel extends Model
{
    protected $table         = 'schedule_recurrences';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['program_year_id', 'course_id', 'slot_id', 'slot_span', 'weekday', 'start_date', 'end_date', 'title', 'group_id', 'audience', 'location', 'lo_la', 'notes'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
