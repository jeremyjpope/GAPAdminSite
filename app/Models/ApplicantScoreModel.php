<?php

namespace App\Models;

use CodeIgniter\Model;

class ApplicantScoreModel extends Model
{
    protected $table         = 'applicant_scores';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['applicant_id', 'user_id', 'scorer_name', 'category', 'score', 'updated_at'];
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
}
