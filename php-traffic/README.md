# Smart City Platform — Traffic & Incident Service

Layanan **Traffic & Incident Service** berbasis **PHP 8.2 Monolithic/MVC Layer** yang bertanggung jawab penuh untuk mengelola pencatatan data sensor lalu lintas otomatis (*IoT Gateway/Node-RED*) serta manajemen pelaporan insiden jalan raya secara manual oleh Operator Kota.

Layanan ini terintegrasi secara asinkron dengan **Python ML Service** melalui message broker RabbitMQ untuk memicu kalkulasi prediksi kepadatan kota secara real-time.

---

## Tech Stack & Komponen
- **Language:** PHP 8.2+ (Pure MVC Architecture / No Heavy Framework)
- **Database:** MySQL (MariaDB) dengan driver PDO (PHP Data Objects)
- **Message Broker:** RabbitMQ (`php-amqplib`) untuk arsitektur *Event-Driven*
- **API Standards:** REST API dengan format respon JSON seragam standar PRD

---

## Struktur Folder Kerja
```text
php-traffic/
├── app/
│   ├── Controllers/      # Logika Bisnis & Validasi Payload API
│   │   ├── IncidentController.php
│   │   └── TrafficController.php
│   ├── Models/           # Query SQL aman (Prepared Statements)
│   │   ├── Incident.php
│   │   └── TrafficData.php
│   ├── Services/         # Integrasi Pihak Ketiga (Asinkron)
│   │   └── RabbitMQPublisher.php
│   └── Database.php      # Singleton Connection Wrapper (PDO)
├── public/
│   └── index.php         # Front Controller / Routing Gate Utama
├── vendor/               # Dependensi Composer (php-amqplib)
├── .env                  # Konfigurasi Lokal Kredensial (Git Ignored)
└── README.md             # Dokumentasi Layanan

```

---

## Panduan Menjalankan di Lokal

### 1. Prasyarat Sistem

Pastikan perangkat kamu sudah mengaktifkan:

* XAMPP / Laragon (PHP 8.2+ & MySQL)
* Extension `sockets` pada `php.ini` harus diaktifkan (Hapus tanda `;` pada `;extension=sockets`)
* Composer terinstal di sistem
* Docker Desktop (Untuk menjalankan container RabbitMQ)

### 2. Setup Environment (`.env`)

Buat berkas bernama `.env` di dalam root folder `php-traffic/` lalu sesuaikan konfigurasinya:

```ini
PORT=8001
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
DB_NAME=smartcity

RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest

```

### 3. Jalankan Message Broker (RabbitMQ)

Buka terminal baru lalu aktifkan container RabbitMQ via Docker:

```bash
docker run -d --name smartcity-rabbitmq -p 5672:5672 -p 15672:15672 rabbitmq:3-management

```

*Dashboard pemantauan antrean dapat diakses di `http://localhost:15672` (user/pass: `guest`).*

### 4. Install Dependensi Vendor & Jalankan Server

Buka terminal di dalam folder `php-traffic/`, lalu jalankan:

```bash
composer install
php -S localhost:8001 -t public/

```

---

## Dokumentasi Endpoint REST API

Semua request dan response wajib menggunakan tipe konten `application/json` dengan format standardisasi PRD.

| Method | Endpoint | Akses Aktor | Deskripsi / Fungsi |
| --- | --- | --- | --- |
| **GET** | `/api/traffic/health` | Public | Pengecekan status web server & konektivitas DB |
| **POST** | `/api/traffic/sensor` | IoT Gateway | Menyimpan data sensor riil & publish ke RabbitMQ |
| **GET** | `/api/traffic/current` | Citizen / Op | Mengambil status kepadatan paling terupdate per zona |
| **GET** | `/api/traffic/zones/{zone}` | Citizen / Op | Menampilkan riwayat log sensor berdasarkan zona (A/B/C) |
| **POST** | `/api/traffic/incidents` | Operator Only | Mencatat pelaporan insiden/kecelakaan jalan raya baru |
| **GET** | `/api/traffic/incidents` | Protected | Melihat daftar seluruh rekam jejak insiden kota |
| **PUT** | `/api/traffic/incidents/{id}` | Operator Only | Memperbarui status penanganan insiden ke `resolved` |

### Contoh Payload POST `/api/traffic/sensor`

```json
{
  "sensor_id": "SENSOR-B-04",
  "zone": "B",
  "vehicle_count": 42,
  "avg_speed": 48.5,
  "congestion_level": 3
}

```

### Contoh Payload POST `/api/traffic/incidents`

```json
{
  "zone": "B",
  "incident_type": "Kecelakaan",
  "description": "Tabrakan beruntun melibatkan 3 kendaraan di jalur tengah."
}

```

---

## Skema Event-Driven Message (RabbitMQ)

Setiap kali endpoint `POST /api/traffic/sensor` sukses menerima kiriman data dari Node-RED, service ini akan mempublikasikan pesan asinkron ke layanan **ML Service** dengan spesifikasi berikut:

* **Exchange:** `city.events` (Type: `topic`)
* **Routing Key:** `traffic.sensor.received`
* **Payload Format (JSON):**
```json
{
  "zone": "B",
  "vehicle_count": 42.0,
  "avg_speed": 48.5,
  "incident": 0
}

```