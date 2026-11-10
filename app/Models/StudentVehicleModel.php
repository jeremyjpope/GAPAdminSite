<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentVehicleModel extends Model
{
    protected $table         = 'student_vehicles';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['student_id', 'description', 'vin', 'oil_type', 'oil_notes'];
    protected $useTimestamps = false;
}
