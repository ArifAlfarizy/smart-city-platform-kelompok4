<?php

namespace App\Models;

use CodeIgniter\Model;

class CitizenModel extends Model {
    protected $table = 'citizens';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'user_id',
        'nik',
        'name',
        'phone'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}