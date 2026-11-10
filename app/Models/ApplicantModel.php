<?php

namespace App\Models;

use CodeIgniter\Model;

class ApplicantModel extends Model
{
    protected $table         = 'applicants';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['program_year_id', 'submission_order', 'first_name', 'last_name', 'preferred_name', 'gender', 'email', 'phone', 'address', 'birthday', 'mom_email', 'dad_email', 'tshirt_size', 'placement_preference', 'allergies_medical', 'cleared_to_drive', 'auto_insurance', 'housing_preference', 'tuition_note', 'mp_link', 'notes', 'status', 'student_id', 'prior_applicant_id'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
