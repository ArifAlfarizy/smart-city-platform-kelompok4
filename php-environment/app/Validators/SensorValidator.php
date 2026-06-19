<?php
// app/Validators/SensorValidator.php
namespace App\Validators;

class SensorValidator
{
    private array $errors = [];

    private const VALID_ZONES = ['A', 'B', 'C', 'D', 'E'];

    // Status string yang valid dari firmware ESP32
    private const VALID_AQI_STATUS = [
        'Baik', 'Sedang', 'Tidak Sehat (Sensitif)',
        'Tidak Sehat', 'Sangat Tidak Sehat', 'Berbahaya',
    ];
    private const VALID_RAIN_STATUS  = ['Tidak Hujan', 'Hujan Ringan', 'Hujan Sedang', 'Hujan Lebat'];
    private const VALID_FLOOD_STATUS = ['Aman', 'Waspada', 'Siaga', 'Bahaya'];

    public function validate(array $data): array
    {
        $this->errors = [];

        if (empty($data['zone'])) {
            $this->errors[] = 'Field zone wajib diisi.';
        } elseif (!in_array(strtoupper($data['zone']), self::VALID_ZONES, true)) {
            $this->errors[] = 'Zone harus salah satu dari: A, B, C, D, E.';
        }

        if (!isset($data['aqi'])) {
            $this->errors[] = 'Field aqi wajib diisi.';
        } elseif (!is_numeric($data['aqi']) || (float)$data['aqi'] < 0) {
            $this->errors[] = 'Field aqi harus berupa angka >= 0.';
        }

        if (!isset($data['temperature'])) {
            $this->errors[] = 'Field temperature wajib diisi.';
        } elseif (!is_numeric($data['temperature']) || (float)$data['temperature'] < -50 || (float)$data['temperature'] > 80) {
            $this->errors[] = 'Field temperature harus antara -50 hingga 80 Celsius.';
        }

        if (!isset($data['humidity'])) {
            $this->errors[] = 'Field humidity wajib diisi.';
        } elseif (!is_numeric($data['humidity']) || (float)$data['humidity'] < 0 || (float)$data['humidity'] > 100) {
            $this->errors[] = 'Field humidity harus antara 0 hingga 100 persen.';
        }

        if (isset($data['flood_level']) && (!is_numeric($data['flood_level']) || (float)$data['flood_level'] < 0)) {
            $this->errors[] = 'Field flood_level harus berupa angka >= 0.';
        }

        if (isset($data['rain_level']) && (!is_numeric($data['rain_level']) || (float)$data['rain_level'] < 0 || (float)$data['rain_level'] > 100)) {
            $this->errors[] = 'Field rain_level harus antara 0 hingga 100.';
        }

        if (isset($data['rain_intensity']) && (!is_numeric($data['rain_intensity']) || (float)$data['rain_intensity'] < 0)) {
            $this->errors[] = 'Field rain_intensity harus berupa angka >= 0.';
        }

        if (!empty($data['sensor_id']) && strlen($data['sensor_id']) > 50) {
            $this->errors[] = 'Field sensor_id maksimal 50 karakter.';
        }

        if (!empty($this->errors)) {
            throw new \InvalidArgumentException(implode(' ', $this->errors));
        }

        return [
            'sensor_id'      => $data['sensor_id']      ?? 'UNKNOWN',
            'zone'           => strtoupper($data['zone']),
            // Udara
            'aqi'            => (float)$data['aqi'],
            'aqi_status'     => $data['aqi_status']      ?? null,
            'pm25'           => isset($data['pm25'])      ? (float)$data['pm25']      : null,
            'pm10'           => isset($data['pm10'])      ? (float)$data['pm10']      : null,
            'no2'            => isset($data['no2'])       ? (float)$data['no2']       : null,
            'co'             => isset($data['co'])        ? (float)$data['co']        : null,
            'o3'             => isset($data['o3'])        ? (float)$data['o3']        : null,
            // Cuaca
            'temperature'    => (float)$data['temperature'],
            'humidity'       => (float)$data['humidity'],
            // Hujan (NEW)
            'rain_level'     => isset($data['rain_level'])     ? (float)$data['rain_level']     : 0.0,
            'rain_intensity' => isset($data['rain_intensity']) ? (float)$data['rain_intensity'] : 0.0,
            'rain_status'    => $data['rain_status']     ?? null,
            // Banjir
            'flood_level'    => (float)($data['flood_level']   ?? 0),
            'flood_status'   => $data['flood_status']    ?? null,
            'recorded_at'    => $data['recorded_at']     ?? date('Y-m-d H:i:s'),
        ];
    }
}
