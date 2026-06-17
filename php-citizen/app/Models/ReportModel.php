<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportModel extends Model {
    protected $table = 'reports';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'citizen_id',
        'category',
        'zone',
        'description',
        'status'
    ];

    protected $useTimestaps =false;
}