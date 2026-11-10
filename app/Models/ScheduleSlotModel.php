<?php

namespace App\Models;

use CodeIgniter\Model;

class ScheduleSlotModel extends Model
{
    protected $table         = 'schedule_slots';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['program_year_id', 'label', 'audience', 'start_time', 'end_time', 'sort_order'];
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
}
