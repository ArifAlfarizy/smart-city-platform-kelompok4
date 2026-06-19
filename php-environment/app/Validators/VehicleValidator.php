<?php
// app/Validators/VehicleValidator.php
namespace App\Validators;

class VehicleValidator
{
    private const VALID_ZONES          = ['A', 'B', 'C', 'D', 'E'];
    private const VALID_TRAFFIC_STATUS = ['Lancar', 'Sedang', 'Padat', 'Macet'];
    private const VALID_RAIN_STATUS    = ['Tidak Hujan', 'Hujan Ringan', 'Hujan Sedang', 'Hujan Lebat'];

    public function validate(array $data): array
    {
        $errors = [];

        // zone — wajib
        if (empty($data['zone'])) {
            $errors[] = 'Field zone wajib diisi.';
        } elseif (!in_array(strtoupper($data['zone']), self::VALID_ZONES, true)) {
            $errors[] = 'Zone harus salah satu dari: A, B, C, D, E.';
        }

        // vehicle_count — wajib, integer >= 0
        if (!isset($data['vehicle_count'])) {
            $errors[] = 'Field vehicle_count wajib diisi.';
        } elseif (!is_numeric($data['vehicle_count']) || (int)$data['vehicle_count'] < 0) {
            $errors[] = 'Field vehicle_count harus integer >= 0.';
        }

        // interval_sec — opsional, default 60
        if (isset($data['interval_sec']) && (!is_numeric($data['interval_sec']) || (int)$data['interval_sec'] <= 0)) {
            $errors[] = 'Field interval_sec harus integer > 0.';
        }

        // rain_intensity — opsional, >= 0
        if (isset($data['rain_intensity']) && (!is_numeric($data['rain_intensity']) || (float)$data['rain_intensity'] < 0)) {
            $errors[] = 'Field rain_intensity harus angka >= 0.';
        }

        // flood_level — opsional, >= 0
        if (isset($data['flood_level']) && (!is_numeric($data['flood_level']) || (float)$data['flood_level'] < 0)) {
            $errors[] = 'Field flood_level harus angka >= 0.';
        }

        // sensor_id panjang max
        if (!empty($data['sensor_id']) && strlen($data['sensor_id']) > 50) {
            $errors[] = 'Field sensor_id maksimal 50 karakter.';
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'sensor_id'      => $data['sensor_id']      ?? 'UNKNOWN',
            'zone'           => strtoupper($data['zone']),
            'vehicle_count'  => (int)$data['vehicle_count'],
            'interval_sec'   => (int)($data['interval_sec']   ?? 60),
            // traffic_status boleh dari firmware, boleh dihitung ulang di model
            'traffic_status' => $data['traffic_status']  ?? null,
            'rain_intensity' => isset($data['rain_intensity']) ? (float)$data['rain_intensity'] : 0.0,
            'rain_status'    => $data['rain_status']     ?? null,
            'flood_level'    => isset($data['flood_level'])    ? (float)$data['flood_level']    : 0.0,
            'recorded_at'    => $data['recorded_at']     ?? date('Y-m-d H:i:s'),
        ];
    }
}
