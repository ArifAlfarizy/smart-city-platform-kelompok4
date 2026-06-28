# Smart City Platform — Kelompok 4

Platform microservices untuk simulasi kota pintar: monitoring lalu lintas, kualitas lingkungan (hujan & ketinggian air), layanan pelaporan warga, dan rekomendasi berbasis Machine Learning — terintegrasi lewat satu API Gateway dengan otentikasi OAuth 2.0.

## Daftar Isi

- [Arsitektur](#arsitektur)
- [Tech Stack](#tech-stack)
- [Prasyarat](#prasyarat)
- [Instalasi](#instalasi)
- [Menjalankan Project](#menjalankan-project)
- [Service & Port](#service--port)
- [Database](#database)
- [Testing Manual](#testing-manual)
- [Troubleshooting](#troubleshooting)
- [Struktur Folder](#struktur-folder)

## Arsitektur

```
                        ┌─────────────────┐
                        │   API Gateway   │  :3000
                        │ (Express + JWT) │
                        └────────┬────────┘
                                 │
        ┌──────────┬────────────┼────────────┬──────────────┐
        │           │            │            │              │
  ┌─────▼────┐ ┌────▼─────┐ ┌────▼─────┐ ┌────▼─────┐  ┌─────▼─────┐
  │   Auth   │ │ Traffic  │ │  Citizen │ │Environment│  │ Python ML │
  │ (Node.js)│ │  (PHP)   │ │ (PHP CI) │ │(PHP Slim) │  │ (FastAPI) │
  │  :3002   │ │  :8001   │ │  :8080   │ │  :8002    │  │  :5001    │
  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬──────┘  └─────┬─────┘
       │            │            │            │               │
       └────────────┴────────────┴────────────┴───────────────┘
                                 │
                  ┌──────────────┴──────────────┐
                  │                              │
            ┌─────▼─────┐                 ┌──────▼──────┐
            │  MySQL 8   │                 │  RabbitMQ   │
            │   :3306    │                 │ :5672/15672 │
            └────────────┘                 └─────────────┘

IoT layer:  ESP32 → Mosquitto (MQTT :1883) → Node-RED (:1880) → API Gateway
```

Semua request publik masuk lewat **API Gateway** (`:3000`). Gateway yang melakukan verifikasi JWT, rate limiting, dan proxy ke service tujuan. Service backend (traffic, citizen, environment) tidak diakses langsung oleh client di production, walau port-nya tetap di-expose untuk keperluan debug.

## Tech Stack

| Layer | Teknologi |
|---|---|
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

## Prasyarat

- [Docker](https://docs.docker.com/get-docker/) & Docker Compose v2 (`docker compose version`)
- Git
- Untuk testing manual via script Python: Python 3.9+ dan `pip install pika`
- (Opsional, kalau mau develop salah satu service tanpa Docker) Node.js 18+, PHP 8.2 + Composer, Python 3.11+

## Instalasi

1. **Clone repository**
   ```bash
   git clone https://github.com/ArifAlfarizy/smart-city-platform-kelompok4
   cd smart-city-platform-kelompok4
   ```

2. **Siapkan environment variable untuk tiap service**

   Setiap service punya `.env.example`. Saat image di-build, Dockerfile otomatis copy `.env.example` → `.env` kalau `.env` belum ada — jadi untuk development cepat, kamu **tidak wajib** bikin `.env` manual. Tapi untuk production atau kalau mau ganti secret, copy manual dan isi:

   ```bash
   cp auth-service/.env.example auth-service/.env
   cp express-gateway/.env.example express-gateway/.env
   cp php-citizen/.env.example php-citizen/.env
   cp php-environment/.env.example php-environment/.env
   cp python-ml-service/.env.example python-ml-service/.env
   ```

   > **Penting:** `JWT_ACCESS_SECRET` harus **identik** di semua service (auth-service, api-gateway, citizen-service, environment-service, python-ml). Kalau beda, verifikasi token akan selalu gagal di service yang secret-nya tidak match. Di `docker-compose.yml`, ini sudah diatur lewat satu variable bersama:
   > ```bash
   > export JWT_ACCESS_SECRET=secret_kamu_minimal_32_karakter
   > ```
   > Kalau tidak di-set, semua service otomatis pakai default `accessrahasia_super_secret_min_32_chars` dari `docker-compose.yml` — aman untuk development, **jangan dipakai di production**.

3. **Build & jalankan semua service**
   ```bash
   docker compose up -d --build
   ```

   Compose akan otomatis: tarik image infrastruktur (MySQL, RabbitMQ, Mosquitto), build image custom tiap service, lalu start sesuai urutan `depends_on` (DB & broker harus `healthy` dulu sebelum service aplikasi start).

## Menjalankan Project

```bash
# Jalankan semua service di background
docker compose up -d --build

# Cek status semua container
docker compose ps

# Lihat log salah satu service (live)
docker compose logs -f api-gateway

# Stop semua service (data di volume tetap ada)
docker compose stop

# Stop & hapus container (volume MySQL tetap aman)
docker compose down

# Hapus container + volume (RESET total database, hati-hati!)
docker compose down -v
```

Modul monitoring (Prometheus & Grafana) memakai Compose **profile**, jadi tidak otomatis ikut start:
```bash
docker compose --profile monitoring up -d
```

### Verifikasi semua service hidup

```bash
curl http://localhost:3000/health   # API Gateway
curl http://localhost:3002/health   # Auth Service
curl http://localhost:8002/health   # Environment Service
curl http://localhost:5001/health   # Python ML Service
```

Semua container harus berstatus `Up ... (healthy)` di `docker compose ps`. Kalau ada yang `unhealthy` atau `Exited`, cek bagian [Troubleshooting](#troubleshooting).

## Service & Port

| Service | Container | Port Host | Akses |
|---|---|---|---|
| API Gateway | `api-gateway` | `3000` | Entry point utama (publik) |
| Auth Service | `auth-service` | `3002` | Lewat gateway / langsung untuk debug |
| Traffic Service | `traffic-service` | `8001` | Lewat gateway |
| Environment Service | `environment-service` | `8002` | Lewat gateway |
| Citizen Service | `citizen-service` | `8080` | Lewat gateway |
| Python ML Service | `python-ml` | `5001` (container: `5000`) | Lewat gateway |
| MySQL | `mysql_db` | `3306` | Internal |
| RabbitMQ | `rabbitmq` | `5672` (AMQP), `15672` (Management UI) | Internal / debug |
| Mosquitto (MQTT) | `mosquitto` | `1883` | IoT |
| Node-RED | `node-red` | `1880` | IoT bridge UI |
| Prometheus *(opsional)* | `prometheus` | `9090` | Monitoring |
| Grafana *(opsional)* | `grafana` | `3001` (container: `3000`) | Monitoring |

RabbitMQ Management UI bisa diakses di `http://localhost:15672` (login: `guest` / `guest`).

## Database

- `database/schema.sql` — definisi semua tabel, otomatis di-load ke MySQL **hanya sekali**, saat volume `mysql_data` masih kosong (perilaku image resmi `mysql:8.0` untuk file di `docker-entrypoint-initdb.d/`).
- `database/seed.sql` — data dummy untuk testing (citizens, reports, traffic_data, incidents, dst), juga di-load di kesempatan yang sama dengan schema.
- `database/migrations/` — riwayat perubahan schema secara incremental, untuk referensi/dokumentasi (tidak di-jalankan otomatis oleh Compose).

> **Penting — perilaku initdb MySQL:** Karena script di `docker-entrypoint-initdb.d/` cuma jalan sekali, **mengubah `schema.sql` tidak akan berefek** ke database yang sudah pernah ke-provision. Kalau kamu ubah struktur tabel dan ingin perubahan itu ke-apply, kamu harus reset volume:
> ```bash
> docker compose down
> docker volume rm smart-city-platform-kelompok4_mysql_data
> docker compose up -d
> ```
> Ini akan **menghapus semua data** di MySQL (semua tabel), tidak cuma yang relevan. Backup dulu dengan `mysqldump` kalau ada data penting yang bukan dari seed.

Cek isi database langsung:
```bash
docker compose exec mysql_db mysql -u root -prootpass smartcity -e "SHOW TABLES;"
docker compose exec mysql_db mysql -u root -prootpass smartcity -e "DESCRIBE environment_data;"
```

## Testing Manual

Tiga script Python di root project mensimulasikan event sensor lewat RabbitMQ langsung (tanpa lewat HTTP/gateway), berguna buat test pipeline ML & consumer:

```bash
pip install pika

# Simulasikan 3 event traffic dari zona berbeda
python test_s1_traffic.py

# Simulasikan event traffic ekstrem (untuk trigger anomaly detection)
python test_s6_sensor.py

# Dengarkan alert anomaly yang dipublish balik oleh python-ml-service
python test_s6_subscriber.py
```

Script-script ini connect ke RabbitMQ di `localhost:5672`, jadi pastikan port itu sudah ter-expose (sudah, lihat tabel port di atas) dan container `rabbitmq` berstatus healthy.

Untuk testing endpoint HTTP lewat Postman, import koleksi yang sudah disediakan:
- `docs/postman.json`
- `docs/postman_environment_service.json`

## Troubleshooting

| Simptom | Kemungkinan Penyebab | Cek |
|---|---|---|
| Container exited / restart loop | Cek exit code & OOM | `docker inspect <container> --format '{{json .State}}'` |
| 502 dari gateway | Service tujuan belum healthy / nama service di `AUTH_SERVICE_URL` dkk salah | `docker compose logs <service>`, cek `express-gateway/src/config/services.js` |
| 500 generik tanpa detail | Hampir selalu ada stack trace lengkap di log container, walau response ke client digeneralisir | `docker compose logs <service> --tail=100` |
| Skema tabel tidak sesuai kode | Volume MySQL sudah terlanjur lama, `schema.sql` baru tidak ke-apply | Lihat bagian [Database](#database) di atas |
| `mysqli`/extension PHP error | Extension belum di-install di Dockerfile service PHP terkait | Cek `docker-php-ext-install` di Dockerfile, lalu `docker compose build <service>` ulang |

Untuk semua kasus debug PHP (Slim maupun CodeIgniter), log error lengkap biasanya tersimpan permanen dan bisa dibaca lewat:
```bash
docker compose logs <service> --tail=100
# atau, untuk CodeIgniter (file log harian)
docker compose exec citizen-service cat /var/www/html/writable/logs/log-$(date +%Y-%m-%d).log
```

## Struktur Folder

```
smart-city-platform-kelompok4/
├── docker-compose.yml
├── auth-service/          # OAuth 2.0 Auth Service (Node.js/Express)
├── express-gateway/       # API Gateway (Node.js/Express)
├── php-traffic/           # Traffic Service (PHP)
├── php-environment/       # Environment Service (PHP Slim)
├── php-citizen/           # Citizen Service (PHP CodeIgniter 4)
├── python-ml-service/     # ML Service (FastAPI)
├── database/              # schema.sql, seed.sql, migrations/
├── iot/                   # ESP32 firmware, Mosquitto config, Node-RED flows
├── k8s/                   # Kubernetes manifests
├── docs/                  # Postman collections
└── test_*.py              # Script simulasi event RabbitMQ
```

Untuk detail masing-masing service, lihat README di folder service terkait (`auth-service/README.md`, `express-gateway/README.md`, dst).
