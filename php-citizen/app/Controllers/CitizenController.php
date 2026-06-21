<?php

namespace App\Controllers;
use App\Models\CitizenModel;

class CitizenController extends BaseController {
    public function profile() 
    {
        $model = new CitizenModel();
        return $this->response->setJSON(
        $model->findAll() // sementara tampil semua data citizen krn blm ada jwt
    );

    }

    public function updateProfile()
    {
        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'update citizen profile'
        ]); // update profile masih dummy, nnt pake user_id dari jwt
    }
}