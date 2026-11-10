<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $table         = 'payments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['student_id', 'paid_on', 'amount', 'method', 'reference', 'note', 'recorded_by'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $updatedField  = '';
}
