<?php

namespace App\Models;

use CodeIgniter\Model;

class ScheduleBlockModel extends Model
{
    protected $table         = 'schedule_blocks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['program_year_id', 'label', 'start_date', 'end_date', 'color'];
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
}
