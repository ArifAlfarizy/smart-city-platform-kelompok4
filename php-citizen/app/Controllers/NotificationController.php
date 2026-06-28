<?php

namespace App\Controllers;

use App\Models\CitizenModel;
use App\Models\NotificationModel;

class NotificationController extends BaseController
{
    private function getCurrentCitizen(): ?array
    {
        $payload = $this->getAuthPayload();

        if (!$payload) {
            return null;
        }

        if (($payload['role'] ?? '') !== 'citizen') {
            return null;
        }

        $userId = (int) ($payload['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $model = new CitizenModel();
        return $model->where('user_id', $userId)->first();
    }

    public function index()
    {
        $payload = $this->getAuthPayload();
        if (!$payload) {
            return $this->jsonError('Token tidak valid atau sudah kedaluwarsa.', 401);
        }

        if (($payload['role'] ?? '') !== 'citizen') {
            return $this->jsonError('Akses ditolak.', 403);
        }

        $citizen = $this->getCurrentCitizen();
        if (!$citizen) {
            return $this->jsonError('Data citizen tidak ditemukan.', 404);
        }

        $model = new NotificationModel();

        $notifications = $model
            ->where('citizen_id', $citizen['id'])
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return $this->jsonSuccess($notifications, 'Notifications berhasil diambil.');
    }
}