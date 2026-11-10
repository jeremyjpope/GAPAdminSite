<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseAssetModel extends Model
{
    protected $table         = 'course_assets';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['course_id', 'title', 'url', 'file_path', 'notes', 'created_at'];
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
}
