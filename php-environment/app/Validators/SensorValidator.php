<?php
// app/Validators/SensorValidator.php
namespace App\Validators;

class SensorValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        // rainfall wajib, harus angka >= 0
        if (!isset($data['rainfall'])) {
            $errors[] = 'Field rainfall wajib diisi.';
        } elseif (!is_numeric($data['rainfall']) || (float)$data['rainfall'] < 0) {
            $errors[] = 'Field rainfall harus berupa angka >= 0 (mm/h).';
        }

        // water_level wajib, harus angka >= 0
        if (!isset($data['water_level'])) {
            $errors[] = 'Field water_level wajib diisi.';
        } elseif (!is_numeric($data['water_level']) || (float)$data['water_level'] < 0) {
            $errors[] = 'Field water_level harus berupa angka >= 0 (cm).';
        }

        // sensor_id opsional tapi kalau ada max 50 char
        if (isset($data['sensor_id']) && strlen((string)$data['sensor_id']) > 50) {
            $errors[] = 'Field sensor_id maksimal 50 karakter.';
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'sensor_id'   => isset($data['sensor_id']) ? (string)$data['sensor_id'] : 'UNKNOWN',
            'rainfall'    => round((float)$data['rainfall'], 2),
            'water_level' => round((float)$data['water_level'], 2),
        ];
    }
}