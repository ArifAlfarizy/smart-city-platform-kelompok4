<?php

namespace App\Models;
use CodeIgniter\Model;

class NotificationModel extends Model{
    protected $table = 'notifications';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'citizen_id',
        'title',
        'message',
        'is_read'
    ];

    protected $useTimestamps = false;
}