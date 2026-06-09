<?php

namespace App\Controllers;
use App\Models\ReportModel;

class ReportController extends BaseController
{
    public function create()
    {
        $model = new ReportModel();

        $data = [
            'citizen_id' => 1, //nnt diambil dr jwt user login
            'category' => 'jalan_rusak',
            'zone' => 'A',
            'description' => 'Dummy report',
            'status' => 'pending'
        ];

        $model->insert($data);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'report created'
        ]);
    }

    public function myReports()
    {
        $model = new ReportModel();
        return $this->response->setJSON(
            $model->findAll() //sm blm ada jwt
    );
    }

    public function allReports() 
    {
        $model = new ReportModel();
        return $this->response->setJSON(
            $model->findAll() //blm ada pembatasan role operator
    );
    }

    public function updateStatus($id)
    {
        $model = new ReportModel();
        $model->update($id, [
            'status' => 'completed'
    ]); // sementara semua user bs update status, nnt dibatasin role operator

    return $this->response->setJSON([
        'status' => 'success',
        'message' => 'report updated'
    ]);
    }
}