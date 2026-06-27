# Smart City Platform — Role 4: Environment Service

Microservice untuk menerima, menyimpan, dan mempublikasikan data sensor lingkungan dari ESP32 via MQTT.

## Stack

- **PHP 8.2** + Slim 4
- **MySQL 8** — penyimpanan data sensor & alert
- **RabbitMQ** — publish event ke python-ml
- **Mosquitto** — MQTT broker untuk ESP32
- **Node-RED** — bridge MQTT ke REST API

---

## IoT Flow

```
ESP32 (Wokwi)
  │  MQTT publish city/ESP32-ENV-01/environment
  │  payload: { sensor_id, rainfall, water_level }
  ▼
Mosquitto :1883
  │  subscribe city/+/environment
  ▼
Node-RED :1880
  │  transform + forward
  ▼
POST /api/environment/sensor
  │  Bearer token (client_credentials)
  ▼
php-environment :8002
  │  simpan ke DB
  │  hitung flood_status
  │  generate alert kalau threshold terlampaui
  ▼
MySQL — tabel environment_data + environment_alerts
  +
RabbitMQ — routing key: environment.sensor.received
```

---

## Sensor yang Dipakai

| Sensor | Pin ESP32 | Field | Satuan |
|--------|-----------|-------|--------|
| HC-SR04 Ultrasonic | TRIG D5, ECHO D18 | `water_level` | cm |
| Rain Sensor (Potentiometer) | D34 (ADC) | `rainfall` | mm/h |

---

## API Endpoints

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| GET | `/health` | — | Health check service & DB |
| POST | `/api/environment/sensor` | Bearer | Terima data sensor dari Node-RED |
| GET | `/api/environment/current` | Bearer | Data terkini (1 row terakhir) |
| GET | `/api/environment/history` | Bearer | Riwayat data |
| GET | `/api/environment/flood-status` | Bearer | Status banjir terkini |
| GET | `/api/environment/alerts` | Bearer | Daftar alert aktif |
| GET | `/api/environment/alerts/{id}` | Bearer | Detail alert |
| PUT | `/api/environment/alerts/{id}/resolve` | Bearer (operator) | Resolve alert |

### Query Parameters

`GET /api/environment/history`
```
?from=2026-06-01&to=2026-06-09&limit=50
```

`GET /api/environment/alerts`
```
?status=active   (default)
?status=all
```

---

## Payload Sensor

**POST** `/api/environment/sensor`

```json
{
  "sensor_id"   : "ESP32-ENV-01",
  "rainfall"    : 15.50,
  "water_level" : 65.20
}
```

| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|------------|
| `sensor_id` | string | tidak | Default: `UNKNOWN` |
| `rainfall` | float | **ya** | >= 0, satuan mm/h |
| `water_level` | float | **ya** | >= 0, satuan cm |

Response 201:
```json
{
  "status"  : "success",
  "code"    : 201,
  "data"    : {
    "id"             : 1,
    "sensor_id"      : "ESP32-ENV-01",
    "rainfall"       : 15.5,
    "water_level"    : 65.2,
    "flood_status"   : "Waspada",
    "recorded_at"    : "2026-06-27 10:00:00",
    "alerts_created" : 1
  },
  "message"   : "Data sensor berhasil disimpan.",
  "timestamp" : "2026-06-27T10:00:00+07:00",
  "service"   : "environment-service"
}
```

---

## Flood Status Threshold

| Ketinggian Air | Status |
|----------------|--------|
| < 50 cm | Aman |
| 50 – 99 cm | Waspada |
| 100 – 149 cm | Siaga |
| >= 150 cm | Bahaya |

---

## Auto Alert

Alert otomatis dibuat saat data masuk melebihi threshold:

| Kondisi | Alert Type | Severity |
|---------|------------|----------|
| water_level >= 50 cm | `FLOOD_HIGH` | WARNING |
| water_level >= 100 cm | `FLOOD_HIGH` | CRITICAL |
| rainfall > 30 mm/h | `RAIN_HEAVY` | WARNING |

Alert duplikat tidak dibuat — jika alert type yang sama sudah `active`, skip.

---

## RabbitMQ Events

### Published

| Routing Key | Kapan |
|-------------|-------|
| `environment.sensor.received` | Setiap data sensor masuk |
| `environment.alert.created` | Setiap alert baru dibuat |

**Payload `environment.sensor.received`:**
```json
{
  "sensor_id"    : "ESP32-ENV-01",
  "rainfall"     : 15.5,
  "water_level"  : 65.2,
  "flood_status" : "Waspada",
  "recorded_at"  : "2026-06-27 10:00:00"
}
```

---

## Setup & Jalankan

### 1. Generate Mosquitto passwd (WAJIB sebelum docker-compose up)

```bash
bash setup_mosquitto.sh
```

Membuat `iot/config/passwd` dengan user `iot_device` / password `iot_secret`.

### 2. Copy .env

```bash
cp php-environment/.env.example php-environment/.env
# Isi JWT_ACCESS_SECRET dengan nilai yang sama dengan auth-service
```

### 3. Jalankan

```bash
docker-compose up -d
```

Tunggu semua service healthy:
```bash
docker-compose ps
```

### 4. Import Node-RED flow

Buka http://localhost:1880 → Menu → Import → pilih file `iot/node-red-data/flows_env.json`

### 5. Test flow end-to-end

```bash
pip install paho-mqtt requests
python3 scripts/test_iot_flow.py
```

---

## Test API Manual (curl)

```bash
# Health check
curl http://localhost:8002/health

# POST data sensor (butuh token)
curl -X POST http://localhost:8002/api/environment/sensor \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"sensor_id":"ESP32-ENV-01","rainfall":15.5,"water_level":65.2}'

# Data terkini
curl http://localhost:8002/api/environment/current \
  -H "Authorization: Bearer <token>"

# Status banjir
curl http://localhost:8002/api/environment/flood-status \
  -H "Authorization: Bearer <token>"

# Alert aktif
curl http://localhost:8002/api/environment/alerts \
  -H "Authorization: Bearer <token>"

# Resolve alert (operator only)
curl -X PUT http://localhost:8002/api/environment/alerts/1/resolve \
  -H "Authorization: Bearer <token_operator>"
```

---

## Cek Data di DB

```bash
# Data sensor terbaru
docker exec -it mysql_db mysql -uenv_user -penv_secret smartcity \
  -e "SELECT * FROM environment_data ORDER BY recorded_at DESC LIMIT 5\G"

# Alert aktif
docker exec -it mysql_db mysql -uenv_user -penv_secret smartcity \
  -e "SELECT * FROM environment_alerts WHERE status='active'\G"
```

---

## Troubleshooting

| Masalah | Penyebab | Fix |
|---------|----------|-----|
| Mosquitto tidak start | `iot/config/passwd` tidak ada | `bash setup_mosquitto.sh` |
| ESP32 gagal connect MQTT | Wrong credentials | Cek `MQTT_USER`/`MQTT_PASSWORD` di .ino |
| Node-RED tidak terima MQTT | Mosquitto belum start | `docker logs mosquitto` |
| php-environment 401 | Token IoT belum ada | Cek node "Simpan token" di Node-RED |
| php-environment 422 | Payload tidak ada rainfall/water_level | Cek node "Debug payload" di Node-RED |
| Data tidak masuk DB | DB belum ready | `docker logs environment-service` |
| Alert tidak muncul | Threshold belum tercapai | water_level < 50 atau rainfall <= 30 |