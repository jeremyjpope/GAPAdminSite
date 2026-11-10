<?php

namespace App\Models;

use CodeIgniter\Model;

class ApplicantStepModel extends Model
{
    protected $table         = 'applicant_steps';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['applicant_id', 'step', 'done', 'done_on', 'note'];
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
}
