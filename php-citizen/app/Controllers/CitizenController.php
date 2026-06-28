<?php

namespace App\Controllers;

use App\Models\CitizenModel;

class CitizenController extends BaseController
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

    public function profile()
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

        return $this->jsonSuccess($citizen, 'Citizen profile berhasil diambil.');
    }

    public function updateProfile()
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

        $input = $this->request->getJSON(true) ?? [];

        $data = array_filter([
            'name'  => $input['name'] ?? null,
            'phone' => $input['phone'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        if ($data === []) {
            return $this->jsonError('Tidak ada data yang ingin diupdate.', 422);
        }

        $model = new CitizenModel();
        $model->update($citizen['id'], $data);

        $updatedCitizen = $model->find($citizen['id']);

        return $this->jsonSuccess($updatedCitizen, 'Citizen profile berhasil diperbarui.');
    }
}