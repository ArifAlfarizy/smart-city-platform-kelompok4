# Smart City Platform — Traffic Decision Support Service

Layanan **Traffic Decision Support Service** berbasis **PHP 8.2 Monolithic/MVC Layer** yang bertanggung jawab sebagai *service* utama dalam mengelola, mencatat, dan menyajikan data sensor arus lalu lintas makro secara otomatis pada kawasan Jalan MT Haryono Jakarta

Layanan ini menjadi pusat hulu data rekayasa lalu lintas yang mempublikasikan data observasi secara asinkron ke **Python ML Service (Role 5)** melalui message broker RabbitMQ untuk melahirkan prediksi kemacetan otomatis dan rekomendasi keputusan taktis bagi Dinas Perhubungan.

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
│   │   └── TrafficController.php
│   ├── Models/           # Query SQL aman (Prepared Statements via PDO)
│   │   └── TrafficData.php
│   ├── Services/         # Integrasi Pihak Ketiga (Asinkron Broker)
│   │   └── RabbitMQPublisher.php
│   └── Database.php      # Singleton Connection Wrapper (PDO)
├── database/
│   └── schema.sql        # Script DDL Pembuatan Tabel `traffic_data`
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

Semua request dan response wajib menggunakan tipe konten `application/json` dengan format standardisasi perusahaan. Endpoint mendukung pemanggilan langsung maupun melalui API Gateway (`/api/...`).

| Method | Endpoint | Akses Aktor | Deskripsi / Fungsi |
| --- | --- | --- | --- |
| **GET** | `/api/traffic/health` | Public | Pengecekan status web server & konektivitas DB |
| **POST** | `/traffic-data` | IoT Gateway / Simulator | Menyimpan data volume & kecepatan lalu lintas terbaru, lalu memicu event ke RabbitMQ |
| **GET** | `/traffic-status` | Dashboard Pemerintah | Mengambil kondisi kepadatan lalu lintas paling mutakhir untuk visualisasi status jalan |
| **GET** | `/traffic-history` | Dashboard / Operator | Menampilkan seluruh rekam jejak log riwayat data sensor lalu lintas |
| **GET** | `/traffic-summary` | Dashboard / Operator | Menyajikan ringkasan agregasi data volume kendaraan harian |

### Contoh Payload POST `/traffic-data`

```json
{
  "road_name": "Jalan MT Haryono",
  "vehicle_count": 145,
  "average_speed": 18.50,
  "congestion_level": "Macet",
  "observation_time": "2026-06-24 11:45:00"
}

```

Catatan: Nilai `congestion_level` wajib berupa string klasifikasi kondisi jalan: `Normal`, `Padat`, `Macet`, atau `Sangat Macet`.

---

## Skema Event-Driven Message (RabbitMQ)

Setiap kali data lalu lintas baru berhasil disimpan melalui endpoint `POST /traffic-data`, service ini secara otomatis akan mempublikasikan pesan asinkron ke exchange broker kelompok:

* **Exchange:** `city.events` (Type: `topic`)
* 
**Routing Key:** `traffic.updated` 


* **Payload Format (JSON):**

```json
{
  "id": 1,
  "road_name": "Jalan MT Haryono",
  "vehicle_count": 145,
  "average_speed": 18.50,
  "congestion_level": "Macet",
  "observation_time": "2026-06-24 11:45:00"
}

```

Pesan ini akan dikonsumsi oleh **ML Service (Role 5)** untuk digabungkan dengan data lingkungan dan laporan insiden guna mengekstrak keputusan rekayasa lalu lintas adaptif.