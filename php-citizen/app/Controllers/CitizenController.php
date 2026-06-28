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

    public function register()
    {
        $payload = $this->getAuthPayload();
        if (!$payload) {
            return $this->jsonError('Token tidak valid atau sudah kedaluwarsa.', 401);
        }

        if (($payload['role'] ?? '') !== 'citizen') {
            return $this->jsonError('Akses ditolak. Endpoint ini hanya untuk role citizen.', 403);
        }

        $userId = (int) ($payload['id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonError('Token tidak valid.', 401);
        }

        $model = new CitizenModel();

        // Idempotency: cegah satu user_id punya lebih dari satu profil citizen
        $existing = $model->where('user_id', $userId)->first();
        if ($existing) {
            return $this->jsonError('Profil citizen untuk akun ini sudah ada.', 409);
        }

        $input = $this->request->getJSON(true) ?? [];

        $nik   = trim((string) ($input['nik'] ?? ''));
        $name  = trim((string) ($input['name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));

        $errors = [];
        if ($nik === '') {
            $errors[] = 'Field nik wajib diisi.';
        } elseif (!preg_match('/^\d{16}$/', $nik)) {
            $errors[] = 'Field nik harus berupa 16 digit angka.';
        }
        if ($name === '') {
            $errors[] = 'Field name wajib diisi.';
        }

        if ($errors !== []) {
            return $this->jsonError(implode(' ', $errors), 422);
        }

        // Cegah NIK yang sama dipakai user lain (UNIQUE constraint di DB jadi pengaman kedua)
        $nikTaken = $model->where('nik', $nik)->first();
        if ($nikTaken) {
            return $this->jsonError('NIK sudah terdaftar untuk akun lain.', 409);
        }

        $citizenId = $model->insert([
            'user_id' => $userId,
            'nik'     => $nik,
            'name'    => $name,
            'phone'   => $phone !== '' ? $phone : null,
        ]);

        if (!$citizenId) {
            return $this->jsonError('Gagal menyimpan profil citizen. Kemungkinan NIK sudah digunakan.', 409);
        }

        $citizen = $model->find($citizenId);

        return $this->jsonSuccess($citizen, 'Profil citizen berhasil dibuat.', 201);
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
            return $this->jsonError('Data citizen tidak ditemukan. Lengkapi profil lewat POST /api/citizens/register.', 404);
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
