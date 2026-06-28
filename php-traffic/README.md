# Smart City Platform — Traffic & Decision Support Service

Layanan **Traffic & Decision Support Service** berbasis **PHP 8.2 Monolithic/MVC Layer** yang bertanggung jawab sebagai *service* utama dalam mengelola, mencatat, menyajikan data sensor arus lalu lintas makro kawasan, serta manajemen penanganan insiden operasional jalan raya oleh Operator Kota.

Layanan ini terintegrasi secara dua arah (*Event-Driven Architecture*) menggunakan RabbitMQ:
1. **Sebagai Publisher**: Mempublikasikan data lalu lintas (`traffic.updated`) ke **ML Service (Role 5)**.
2. **Sebagai Consumer**: Mendengarkan dan mereplikasi laporan aduan warga (`incident.created`) dari **Citizen Service (Role 2)**.

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
│   ├── Models/           # Query SQL aman (Prepared Statements via PDO)
│   │   ├── Incident.php
│   │   └── TrafficData.php
│   ├── Services/         # Integrasi Pihak Ketiga (Broker Publisher & Consumer)
│   │   ├── RabbitMQConsumer.php
│   │   └── RabbitMQPublisher.php
│   └── Database.php      # Singleton Connection Wrapper (PDO)
├── database/
│   └── schema.sql        # Script DDL Pembuatan Tabel `traffic_data` & `incidents`
├── public/
│   └── index.php         # Front Controller / Routing Gate Utama
├── vendor/               # Dependensi Composer (php-amqplib)
├── .env                  # Konfigurasi Lokal Kredensial (Git Ignored)
├── worker.php            # Script Background CLI untuk Menjalankan Consumer RabbitMQ
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

### 4. Install Dependensi Vendor & Jalankan Server API

Buka terminal di dalam folder `php-traffic/`, lalu jalankan:

```bash
composer install
php -S localhost:8001 -t public/

```

### 5. Jalankan Background Consumer Worker

Buka terminal terpisah di dalam folder `php-traffic/`, kemudian jalankan skrip worker untuk mulai mendengarkan antrean data dari Citizen Service:

```bash
php worker.php

```

---

## Dokumentasi Endpoint REST API

Semua request dan response wajib menggunakan tipe konten `application/json` dengan format standardisasi perusahaan.

### Log Lalu Lintas (Traffic Data)

| Method | Endpoint | Akses Aktor | Deskripsi / Fungsi |
| --- | --- | --- | --- |
| **GET** | `/api/traffic/health` | Public | Pengecekan status web server & konektivitas DB |
| **POST** | `/traffic-data` | IoT Gateway | Menyimpan data volume & kecepatan lalu lintas terbaru, lalu memicu event ke RabbitMQ |
| **GET** | `/traffic-status` | Dashboard | Mengambil kondisi kepadatan lalu lintas paling mutakhir |
| **GET** | `/traffic-history` | Dashboard | Menampilkan seluruh rekam jejak log riwayat data sensor |
| **GET** | `/traffic-summary` | Dashboard | Menyajikan ringkasan agregasi data volume kendaraan harian |

### Manajemen Insiden Internal (Incidents)

| Method | Endpoint | Akses Aktor | Deskripsi / Fungsi |
| --- | --- | --- | --- |
| **POST** | `/api/traffic/incidents` | Operator Only | Mencatat pelaporan insiden/kecelakaan jalan raya baru secara manual |
| **GET** | `/api/traffic/incidents` | Protected | Melihat daftar seluruh rekam jejak insiden di jalan raya |
| **PUT** | `/api/traffic/incidents/{id}` | Operator Only | Memperbarui status penanganan insiden ke `resolved` |

*Catatan untuk endpoint Operator: Wajib menyertakan JWT Token dengan klaim `"role": "operator"` pada header HTTP Authorization (Format: `Bearer <token>`).*

---

## Skema Transmisi Pesan Event-Driven (RabbitMQ)

### 1. Peran Sebagai Publisher (Event: `traffic.updated`)

Setiap data lalu lintas baru sukses disimpan melalui endpoint `POST /traffic-data`, service ini akan mempublikasikan pesan asinkron bersih ke **ML Service (Role 5)**:

* **Exchange:** `city.events` (Type: `topic`)
* 
**Routing Key:** `traffic.updated` 


* **Payload Format (JSON):**

```json
{
  "road_name": "Jalan MT Haryono",
  "vehicle_count": 850,
  "average_speed": 28.50,
  "observation_time": "2026-06-24 18:30:00"
}

```

### 2. Peran Sebagai Consumer (Event: `incident.created`)

Skrip background `worker.php` bertindak sebagai consumer aktif yang mendengarkan event aduan warga dari **Citizen Service (Role 2)**  untuk direplikasi secara otomatis ke dalam database lokal:

* **Exchange:** `city.events` (Type: `topic`)
* 
**Routing Key:** `incident.created` 


* **Payload Format yang Diterima (JSON):**

```json
{
  "road_name": "Jalan MT Haryono",
  "incident_type": "accident",
  "description": "Kecelakaan beruntun di dekat stasiun Cawang"
}