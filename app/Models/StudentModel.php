<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table         = 'students';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['program_year_id', 'moodle_userid', 'first_name', 'last_name', 'preferred_name', 'email', 'phone', 'gender', 'birthday', 'address', 'tshirt_size', 'placement_preference', 'allergies_medical', 'cleared_to_drive', 'auto_insurance', 'housing_preference', 'host_home_id', 'mentor_id', 'photo_path', 'status', 'attended_from', 'attended_to', 'notes'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
