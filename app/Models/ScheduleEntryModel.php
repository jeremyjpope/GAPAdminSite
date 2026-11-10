<?php

namespace App\Models;

use CodeIgniter\Model;

class ScheduleEntryModel extends Model
{
    protected $table         = 'schedule_entries';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['program_year_id', 'course_id', 'slot_id', 'slot_span', 'start_date', 'end_date', 'title', 'group_id', 'group_label', 'audience', 'location', 'lo_la', 'status', 'comment', 'recurrence_id'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
