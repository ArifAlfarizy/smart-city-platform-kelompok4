<?php

namespace App\Controllers;

use App\Models\CitizenModel;
use App\Models\ReportModel;
use App\Services\RabbitMQPublisher;

class ReportController extends BaseController
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

    private function isOperator(): bool
    {
        $payload = $this->getAuthPayload();
        return $payload !== null && (($payload['role'] ?? '') === 'operator');
    }

    public function create()
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

        $roadName = trim((string) ($input['road_name'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));

        $allowedCategories = [
            'accident',
            'broken_vehicle',
            'fallen_tree',
            'flood',
            'road_obstacle',
            'traffic_light_damage',
        ];

        if ($roadName === '' || $category === '' || $description === '') {
            return $this->jsonError('road_name, category, dan description wajib diisi.', 422);
        }

        if (!in_array($category, $allowedCategories, true)) {
            return $this->jsonError('Kategori laporan tidak valid.', 422);
        }

        $model = new ReportModel();

        $data = [
            'citizen_id' => $citizen['id'],
            'road_name'   => $roadName,
            'category'    => $category,
            'description' => $description,
            'status'      => 'pending',
        ];

        $model->insert($data);

        $reportId = $model->getInsertID();

        $eventPayload = [
            'incident_id' => $reportId,
            'category'    => $category,
            'road_name'   => $roadName,
            'description' => $description,
        ];

        $publisher = new RabbitMQPublisher();

        $published = $publisher->publish(
            'incident.created',
            $eventPayload
        );

        return $this->jsonSuccess([
            'report_id' => $reportId,
            'rabbitmq'  => $published,
            'event'     => 'incident.created'
        ], 'Report berhasil dibuat.', 201);
    }

    public function myReports()
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

        $model = new ReportModel();
        $reports = $model
            ->where('citizen_id', $citizen['id'])
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return $this->jsonSuccess($reports, 'My reports berhasil diambil.');
    }

    public function allReports()
    {
        if (!$this->isOperator()) {
            return $this->jsonError('Akses ditolak. Hanya operator.', 403);
        }

        $model = new ReportModel();

        return $this->jsonSuccess(
            $model->orderBy('created_at', 'DESC')->findAll(),
            'Semua reports berhasil diambil.'
        );
    }

    public function updateStatus($id)
    {
        if (!$this->isOperator()) {
            return $this->jsonError('Akses ditolak. Hanya operator.', 403);
        }

        $model = new ReportModel();
        $report = $model->find($id);

        if (!$report) {
            return $this->jsonError('Report tidak ditemukan.', 404);
        }

        $model->update($id, [
            'status' => 'completed',
        ]);

        return $this->jsonSuccess([
            'id'     => (int) $id,
            'status' => 'completed',
        ], 'Status report berhasil diperbarui.');
    }
}