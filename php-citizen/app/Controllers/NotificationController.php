<?php

namespace App\Controllers;
use App\Models\NotificationModel;

class NotificationController extends BaseController
{
    public function index()
    {
        $model = new NotificationModel();

        return $this->response->setJSON(
            $model->findAll() // sementara tampil semua notif krn blm ada filter user login
        );
    }
}