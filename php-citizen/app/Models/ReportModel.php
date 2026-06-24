<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportModel extends Model
{
    protected $table = 'reports';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'citizen_id',
        'road_name',
        'category',
        'description',
        'status'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}