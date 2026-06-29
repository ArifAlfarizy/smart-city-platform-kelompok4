# Smart City Platform — Kelompok 4

Platform microservices untuk simulasi kota pintar: monitoring lalu lintas, kualitas lingkungan (hujan & ketinggian air), layanan pelaporan warga, dan rekomendasi berbasis Machine Learning — terintegrasi lewat satu API Gateway dengan otentikasi OAuth 2.0. Seluruh layanan ini telah di-deploy dan berjalan di lingkungan server produksi.

## Daftar Isi

- [Arsitektur](#arsitektur)
- [Tech Stack](#tech-stack)
- [Prasyarat Server](#prasyarat-server)
- [Manajemen Proyek di Server](#manajemen-proyek-di-server)
- [Service & Port (Server)](#service--port-server)
- [Database Server](#database-server)
- [Skenario Pengujian (Demo) & Endpoint API](#skenario-pengujian-demo--endpoint-api)
- [Troubleshooting Server](#troubleshooting-server)
- [Struktur Folder](#struktur-folder)

## Arsitektur

```text
                        ┌─────────────────┐
                        │   API Gateway   │  :3040 (Public)
                        │ (Express + JWT) │
                        └────────┬────────┘
                                 │
        ┌──────────┬────────────┼────────────┬──────────────┐
        │           │            │            │              │
  ┌─────▼────┐ ┌────▼─────┐ ┌────▼─────┐ ┌────▼─────┐  ┌─────▼─────┐
  │   Auth   │ │ Traffic  │ │  Citizen │ │Environment│  │ Python ML │
  │ (Node.js)│ │  (PHP)   │ │ (PHP CI) │ │(PHP Slim) │  │ (FastAPI) │
  │  :3042   │ │  :8041   │ │  :8040   │ │  :8042    │  │  :5040    │
  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬──────┘  └─────┬─────┘
       │            │            │            │               │
       └────────────┴────────────┴────────────┴───────────────┘
                                 │
                  ┌──────────────┴──────────────┐
                  │                              │
            ┌─────▼─────┐                 ┌──────▼──────┐
            │  MySQL 8   │                 │  RabbitMQ   │
            │   :3340    │                 │ :5640/15640 │
            └────────────┘                 └─────────────┘

IoT layer:  ESP32 → Mosquitto (MQTT :1840) → Node-RED (:1884) → API Gateway

```

Semua request publik masuk lewat **API Gateway** (`:3040`). Gateway melakukan verifikasi JWT, rate limiting, dan proxy ke service tujuan. Service backend tidak diakses langsung oleh publik di production, namun port host tetap di-expose untuk keperluan pengujian dan debug kelompok.

## Tech Stack

| Layer | Teknologi |
| --- | --- |
| API Gateway | Node.js, Express 5, `http-proxy-middleware`, `jsonwebtoken` |
| Auth Service | Node.js, Express 5, MySQL (`mysql2`), `bcrypt`, OAuth2-style grants |
| Traffic & Environment Service | PHP 8.2, Slim Framework |
| Citizen Service | PHP 8.2, CodeIgniter 4 |
| ML Service | Python, FastAPI, scikit-learn |
| Message Broker | RabbitMQ (topic exchange `city.events`) |
| Database | MySQL 8.0 |
| IoT | ESP32, MQTT (Mosquitto), Node-RED |
| Orkestrasi | Docker Compose, Kubernetes manifests (`/k8s`) |
| Monitoring (opsional) | Prometheus, Grafana |

## Prasyarat Server

* Docker & Docker Compose v2 terinstal di server.
* Akses SSH ke instance server `103.147.92.135`.
* Python 3.9+ (untuk menjalankan script simulasi/subscriber *event* di terminal server).

---

## Manajemen Proyek di Server

Untuk melakukan pemeliharaan, pengecekan log, atau *restart* kontainer, silakan masuk ke server dan akses direktori proyek yang telah dikonfigurasi:

```bash
# 1. Pindah ke direktori proyek di server
cd ~/Kelompok4_SmartCityPlatform/smart-city-platform-kelompok4

# 2. Periksa status dan kesehatan seluruh kontainer
docker compose ps

# 3. Jalankan atau build ulang semua service di background jika ada perubahan kode
docker compose up -d --build

# 4. Memantau log aktivitas API Gateway secara live
docker compose logs -f api-gateway

# 5. Menghentikan seluruh layanan platform
docker compose stop

```

### Verifikasi Health Check Layanan (Akses Publik)

Setiap layanan menyediakan endpoint *health check* yang dapat diakses langsung menggunakan IP server:

```bash
curl [http://103.147.92.135:3040/health](http://103.147.92.135:3040/health)   # API Gateway
curl [http://103.147.92.135:3042/health](http://103.147.92.135:3042/health)   # Auth Service
curl [http://103.147.92.135:8042/health](http://103.147.92.135:8042/health)   # Environment Service
curl [http://103.147.92.135:5040/health](http://103.147.92.135:5040/health)   # ML Service

```

---

## Service & Port (Server)

Sesuai ketentuan Kelompok 4, semua port luar (host) menggunakan konfigurasi dengan akhiran **40** (atau polanya).

| Service | Container Name | Port Host (Server) | Port Container | Akses Publik / internal |
| --- | --- | --- | --- | --- |
| API Gateway | `api-gateway` | `3040` | `3000` | Entry point utama (`http://103.147.92.135:3040`) |
| Auth Service | `auth-service` | `3042` | `3002` | Autentikasi / OAuth 2.0 |
| Traffic Service | `traffic-service` | `8041` | `8001` | Manajemen Lalu Lintas |
| Environment Service | `environment-service` | `8042` | `8002` | Monitoring Lingkungan |
| Citizen Service | `citizen-service` | `8040` | `8080` | Layanan Laporan Warga |
| Python ML Service | `python-ml` | `5040` | `5000` | Analisis & Prediksi ML |
| MySQL Database | `mysql_db` | `3340` | `3306` | Penyimpanan Data Terpusat |
| RabbitMQ (AMQP) | `rabbitmq` | `5640` | `5672` | Broker data sensor / anomali |
| RabbitMQ (UI) | `rabbitmq` | `15640` | `15672` | Management UI (`guest`/`guest`) |
| Mosquitto (MQTT) | `mosquitto` | `1840` | `1883` | Protokol IoT Broker |
| Node-RED | `node-red` | `1884` | `1880` | Antarmuka IoT Bridge |
| Prometheus *(opsional)* | `prometheus` | `9040` | `9090` | Kolektor Metrik Monitoring |
| Grafana *(opsional)* | `grafana` | `3041` | `3000` | Visualisasi Monitoring |

---

## Database Server

* Struktur tabel (`database/schema.sql`) dan data dummy awal (`database/seed.sql`) otomatis dimuat saat kontainer database pertama kali dibuat di server.
* Untuk memeriksa isi tabel database secara langsung dari terminal server:
```bash
docker compose exec mysql_db mysql -u root -prootpass smartcity -e "SHOW TABLES;"
docker compose exec mysql_db mysql -u root -prootpass smartcity -e "SELECT * FROM environment_data ORDER BY id DESC LIMIT 5;"

```



---

## Skenario Pengujian (Demo) & Endpoint API

Pengujian dilakukan menggunakan Postman Collection (`docs/postman.json`) yang diarahkan ke base URL server `http://103.147.92.135:3040`. Berikut adalah detail teknis dari 6 skenario demo utama:

### 3.1 Skenario 1: IoT Data Ingestion

Menunjukkan alur data dari sensor menuju Environment Service melalui MQTT, Node-RED, dan API Gateway.

* **Akses Antarmuka:** Buka Node-RED di browser lewat `http://103.147.92.135:1884`.
* **Prosedur:** Lakukan *inject* data sensor pada flow "Environment IoT Bridge".
* **Endpoint HTTP Alternatif (Gateway):**
* **POST** `http://103.147.92.135:3040/api/environment/sensor`
* *Headers:* `Authorization: Bearer <TOKEN>`
* *Payload:* `{"sensor_id": "ESP32-A-01", "rainfall": 5.0, "water_level": 3.0}`



### 3.2 Skenario 2: Citizen Login & Report

Menunjukkan alur pembuatan akun warga, autentikasi OAuth 2.0, hingga pengiriman laporan insiden.

* **1. Registrasi Akun Warga (POST):** `http://103.147.92.135:3040/auth/register`
* *Payload:* `{"name": "Demo User", "email": "demo@test.com", "password": "password123", "role": "citizen"}`


* **2. Login Akun (POST):** `http://103.147.92.135:3040/auth/login`
* *Payload:* `{"email": "demo@test.com", "password": "password123"}` *(Simpan `access_token` dari response)*


* **3. Membuat Laporan Warga (POST):** `http://103.147.92.135:3040/api/citizens/reports`
* *Headers:* `Authorization: Bearer <TOKEN>`
* *Payload:* `{"road_name": "Jalan MT Haryono", "category": "accident", "description": "Kecelakaan di Simpang Cawang"}`


* **4. Melihat Laporan Saya (GET):** `http://103.147.92.135:3040/api/citizens/reports`

### 3.3 Skenario 3: ML Real-time Prediction

Pengujian integrasi model Machine Learning untuk prediksi performa kota pintar (Wajib menyertakan header `Authorization: Bearer <TOKEN>`):

* **1. Analisis Kemacetan & Rekomendasi (POST):** `http://103.147.92.135:3040/api/ml/analyze`
* *Payload:* `{"vehicle_count": 1200, "average_speed": 18.5, "rainfall": 35.0, "water_level": 420.0, "incident_count": 1}`


* **2. Prediksi Volume Kendaraan (POST):** `http://103.147.92.135:3040/api/ml/predict/volume`
* *Payload:* `{"hour": 8, "day_of_week": 1, "rainfall": 5.0, "water_level": 200.0, "incident_count": 0}`


* **3. Prediksi Risiko Insiden (POST):** `http://103.147.92.135:3040/api/ml/predict/incident-risk`
* *Payload:* `{"vehicle_count": 1200, "average_speed": 18.5, "rainfall": 35.0, "water_level": 420.0, "hour": 8, "day_of_week": 1}`



### 3.4 Skenario 4: Docker Compose Full Stack

Memastikan orkestrasi seluruh kontainer di server berjalan normal tanpa hambatan:

```bash
docker compose ps
docker compose logs --tail=10

```

Seluruh komponen infrastruktur kelompok harus berstatus `Up (healthy)`.

### 3.5 Skenario 5: Kubernetes Deployment (Opsional)

Pemeriksaan kluster orkestrasi alternatif jika diaktifkan di server:

```bash
kubectl get pods -n smartcity
kubectl get svc -n smartcity
kubectl get hpa -n smartcity

```

### 3.6 Skenario 6: Anomaly Alert Flow

Pengujian deteksi otomatis berbasis *event-driven* menggunakan script Python untuk memantau antrean RabbitMQ (Topic Exchange: `city.events`) di port host `5640`. Buka dua tab terminal server:

* **Terminal Server 1 (Jalankan Subscriber):** `python test_s6_subscriber.py`
* **Terminal Server 2 (Kirim Data Ekstrem):** `python test_s6_sensor.py`
* *Ekspektasi:* Terminal 1 akan langsung menampilkan log notifikasi `"ANOMALY ALERT RECEIVED!"` secara real-time.

---

## Troubleshooting Server

| Masalah | Kemungkinan Penyebab | Tindakan Pengecekan |
| --- | --- | --- |
| Response Gateway `502 Bad Gateway` | Service tujuan belum berstatus healthy atau sedang *crash loop*. | Jalankan `docker compose logs <nama-service>` untuk melihat stack trace error internal aplikasi. |
| Perubahan skema DB tidak muncul | Volume data MySQL versi lama masih tersimpan secara permanen di server. | Reset data volume dengan: `docker compose down -v` lalu jalankan kembali `docker compose up -d`. |
| Token JWT selalu ditolak (`401 Unauthorized`) | Nilai variabel `JWT_ACCESS_SECRET` tidak sinkron antar service di server. | Pastikan environment global di server sudah terekspor dengan benar atau periksa fallback default di file compose. |

## Struktur Folder

```text
smart-city-platform-kelompok4/
├── docker-compose.yml
├── auth-service/          # OAuth 2.0 Auth Service (Node.js/Express)
├── express-gateway/       # API Gateway (Node.js/Express)
├── php-traffic/           # Traffic Service (PHP)
├── php-environment/       # Environment Service (PHP Slim)
├── php-citizen/           # Citizen Service (PHP CodeIgniter 4)
├── python-ml-service/     # ML Service (FastAPI)
├── database/              # schema.sql, seed.sql
├── iot/                   # Mosquitto config, Node-RED flows
├── monitoring/            # Konfigurasi Prometheus & Grafana
├── docs/                  # Koleksi Postman & Berkas Laporan Panduan Demo
└── test_*.py              # Script simulasi event RabbitMQ

```

```

```