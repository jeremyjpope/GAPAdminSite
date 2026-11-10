<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['moodle_userid', 'username', 'email', 'fullname', 'is_superadmin', 'is_active', 'last_login_at'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
