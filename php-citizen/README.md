# Citizen Service

Citizen Service merupakan sebuah microservice pada **Smart Traffic Decision Support System** yang bertanggung jawab dalam mengelola data warga (*citizen*) dan laporan insiden. Service ini memungkinkan warga untuk mengirimkan laporan insiden, mengelola profil, melihat riwayat laporan serta notifikasi, dan mengirimkan event ke RabbitMQ sebagai media komunikasi antar-microservice.

---

## Overview

### Features

* Melakukan autentikasi pengguna menggunakan JWT yang diterbitkan oleh **Auth Service** (diverifikasi secara lokal, lihat [Autentikasi](#autentikasi))
* Mendaftarkan profil warga (*citizen profile*) untuk akun yang sudah punya akun di Auth Service
* Melihat profil warga (*citizen profile*)
* Memperbarui profil warga
* Membuat laporan insiden
* Melihat laporan milik sendiri
* Melihat seluruh laporan (Operator)
* Memperbarui status laporan (Operator)
* Melihat notifikasi warga
* Mengirim event `incident.created` ke RabbitMQ ketika laporan berhasil dibuat
* Health check service (`GET /health`)

---

## Technology Stack

* CodeIgniter 4
* PHP 8+
* MySQL
* JWT Authentication (verifikasi lokal HS256, lihat [Autentikasi](#autentikasi))
* RabbitMQ
* php-amqplib/php-amqplib

---

## Database Structure

### `citizens`

Menyimpan informasi warga yang terhubung dengan pengguna dari **Auth Service**.

| Field        | Description               |
| ------------ | ------------------------- |
| `id`         | Primary key               |
| `user_id`    | User ID dari Auth Service |
| `nik`        | Nomor Induk Kependudukan  |
| `name`       | Nama warga                |
| `phone`      | Nomor telepon             |
| `created_at` | Waktu data dibuat         |

### `reports`

Menyimpan laporan insiden yang dikirimkan oleh warga.

| Field         | Description                |
| ------------- | -------------------------- |
| `id`          | Primary key                |
| `citizen_id`  | Data warga yang terkait    |
| `road_name`   | Nama jalan lokasi kejadian |
| `category`    | Kategori insiden           |
| `description` | Deskripsi insiden          |
| `status`      | Status laporan             |
| `created_at`  | Waktu laporan dibuat       |

#### Report Categories

* `accident`
* `broken_vehicle`
* `fallen_tree`
* `flood`
* `road_obstacle`
* `traffic_light_damage`

#### Report Status

* `pending` — status awal saat laporan dibuat
* `process` — belum dipakai di kode saat ini, tersedia di enum DB untuk pengembangan ke depan
* `completed` — diset otomatis saat Operator memanggil `PUT /api/citizens/reports/{id}/status`

### `notifications`

Menyimpan notifikasi yang dikirimkan kepada warga.

| Field        | Description                    |
| ------------ | ------------------------------ |
| `id`         | Primary key                    |
| `citizen_id` | Data warga yang terkait        |
| `title`      | Judul notifikasi               |
| `message`    | Isi notifikasi                 |
| `is_read`    | Status sudah dibaca atau belum |
| `created_at` | Waktu notifikasi dibuat        |

---

## API Endpoints

### Health Check

| Method | Endpoint  | Auth     | Description                              |
| ------ | --------- | -------- | ----------------------------------------- |
| GET    | `/health` | Public   | Cek status service (tidak butuh JWT)      |

### Citizen

| Method | Endpoint                  | Description                                                    |
| ------ | -------------------------- | ---------------------------------------------------------------|
| POST   | `/api/citizens/register`  | Membuat profil citizen baru untuk akun yang sedang login        |
| GET    | `/api/citizens/profile`    | Mengambil data profil warga                                     |
| PUT    | `/api/citizens/profile`    | Memperbarui data profil warga                                   |

> **Penting:** akun yang dibuat lewat **Auth Service** (`POST /auth/register`) belum otomatis punya profil citizen. `GET /api/citizens/profile` akan balas `404` dengan pesan untuk melengkapi profil dulu lewat `POST /api/citizens/register`, sebelum bisa membuat laporan (`POST /api/citizens/reports` juga butuh profil citizen yang sudah ada). Lihat [Authentication & Onboarding Flow](#authentication--onboarding-flow).

### Reports

| Method | Endpoint                            | Description                                             |
| ------ | ----------------------------------- | -------------------------------------------------------- |
| POST   | `/api/citizens/reports`             | Membuat laporan insiden baru                            |
| GET    | `/api/citizens/reports`             | Mengambil seluruh laporan milik warga yang sedang login |
| GET    | `/api/citizens/reports/all`         | Mengambil seluruh laporan (khusus Operator)             |
| PUT    | `/api/citizens/reports/{id}/status` | Menandai laporan menjadi `completed` (khusus Operator)  |

### Notifications

| Method | Endpoint                      | Description                       |
| ------ | ------------------------------ | ---------------------------------- |
| GET    | `/api/citizens/notifications` | Mengambil daftar notifikasi warga |

---

## Request Examples

### Register Citizen Profile

**POST** `/api/citizens/register`

Dipanggil sekali per akun (role `citizen`) setelah login di Auth Service, untuk melengkapi data NIK/nama/telepon sebelum bisa pakai endpoint profile/reports lainnya.

```http
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "nik": "3175012345670001",
  "name": "Budi Santoso",
  "phone": "08123456789"
}
```

Validasi:
- `nik` wajib, harus tepat 16 digit angka, dan belum dipakai akun lain.
- `name` wajib.
- `phone` opsional.

Response sukses (`201`):
```json
{
  "status": "success",
  "message": "Profil citizen berhasil dibuat.",
  "data": {
    "id": 1,
    "user_id": 7,
    "nik": "3175012345670001",
    "name": "Budi Santoso",
    "phone": "08123456789",
    "created_at": "2026-06-27 10:00:00"
  }
}
```

Error yang mungkin terjadi:
| Status | Kondisi |
|---|---|
| `401` | Token tidak ada/tidak valid |
| `403` | Role token bukan `citizen` |
| `409` | Akun ini sudah punya profil citizen, atau NIK sudah dipakai akun lain |
| `422` | `nik`/`name` kosong, atau `nik` bukan 16 digit |

### Create Report

**POST** `/api/citizens/reports`

```json
{
  "road_name": "MT Haryono",
  "category": "accident",
  "description": "Kecelakaan di simpang Cawang"
}
```

> Endpoint ini akan balas `404` kalau akun belum punya profil citizen — lengkapi dulu lewat `POST /api/citizens/register`.

### Update Profile

**PUT** `/api/citizens/profile`

```json
{
  "name": "Budi Santoso",
  "phone": "08123456789"
}
```

### Update Report Status (Operator)

**PUT** `/api/citizens/reports/{id}/status`

Tidak memerlukan request body — endpoint ini akan langsung menandai laporan menjadi `completed`.

```http
Authorization: Bearer <token_operator>
```

Response sukses (`200`):
```json
{
  "status": "success",
  "message": "Status report berhasil diperbarui.",
  "data": { "id": 1, "status": "completed" }
}
```

---

## Authentication & Onboarding Flow

Seluruh endpoint di bawah `/api/citizens/*` menggunakan autentikasi JWT.

```http
Authorization: Bearer <token>
```

JWT diperoleh melalui **Auth Service**. Berbeda dengan API Gateway (yang punya fallback ke `/oauth/introspect`), Citizen Service **memverifikasi JWT secara lokal** — signature `HS256` divalidasi langsung pakai `JWT_ACCESS_SECRET` tanpa round-trip ke Auth Service (lihat `app/Filters/JwtFilter.php` & `BaseController::decodeJwtPayload`). Implikasinya: `JWT_ACCESS_SECRET` di Citizen Service **wajib identik** dengan yang dipakai Auth Service untuk sign token, kalau tidak semua request akan selalu balas `403 Invalid token.`.

Urutan onboarding warga baru, end-to-end:

1. **Register & login di Auth Service** → dapat `access_token` dengan `role: "citizen"`.
2. **`POST /api/citizens/register`** di Citizen Service → melengkapi NIK/nama/telepon, membuat baris di tabel `citizens`.
3. Baru setelah langkah 2 selesai, endpoint `profile`, `reports`, dan `notifications` bisa dipakai — semuanya mencari data lewat `citizens.user_id` yang diambil dari klaim `id` di JWT.

Role yang diperiksa di tiap endpoint:
- `register`, `profile`, `updateProfile`, `create` (report), `myReports`, `notifications` → JWT harus punya `role: "citizen"`.
- `allReports`, `updateStatus` → JWT harus punya `role: "operator"`.

---

## RabbitMQ Integration

Setiap kali warga berhasil membuat laporan, **Citizen Service** akan mengirimkan event ke RabbitMQ.

**Exchange**

```text
city.events
```

**Routing Key**

```text
incident.created
```

**Payload Example**

```json
{
  "incident_id": 1,
  "category": "accident",
  "road_name": "MT Haryono",
  "description": "Kecelakaan beruntun di dekat stasiun Cawang"
}
```

Apabila RabbitMQ tidak tersedia, laporan tetap akan disimpan ke database. Hanya proses pengiriman event yang dilewati — response `POST /api/citizens/reports` tetap balas `201`, dengan field `rabbitmq: false` menandakan publish event gagal.

---

## Local Setup

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

Salin file environment.

```bash
cp .env.example .env
```

Variable yang dibaca dari `.env` (lihat `.env.example`):

| Variable | Wajib | Keterangan |
|---|---|---|
| `CI_ENVIRONMENT` | — | `development` untuk lokal, `production` untuk Docker |
| `app.baseURL` | — | Default `http://localhost:8080/` |
| `database.default.hostname` | ✅ | Host MySQL (`localhost` standalone, `mysql_db` via Docker Compose) |
| `database.default.database` | ✅ | Default `smartcity` |
| `database.default.username` / `password` | ✅ | Kredensial MySQL |
| `database.default.DBDriver` | ✅ | `MySQLi` — butuh extension `mysqli` aktif di PHP |
| `database.default.port` | ✅ | Default `3306` |
| `JWT_ACCESS_SECRET` | ✅ | **Harus identik** dengan secret yang dipakai Auth Service untuk sign token |
| `RABBITMQ_HOST` / `PORT` / `USER` / `PASSWORD` / `VHOST` | ✅ | Koneksi ke RabbitMQ |
| `RABBITMQ_EXCHANGE` | — | Default `city.events` |

### 3. Import Database

Import schema database dan data awal (*seed*) untuk **Citizen Service**.

### 4. Run the Application

```bash
php spark serve
```

Secara default aplikasi akan berjalan pada:

```text
http://localhost:8080
```

### 5. Test the API

Gunakan Postman atau API client lainnya dengan header berikut:

```http
Authorization: Bearer <JWT_TOKEN>
```

Cek dulu service hidup tanpa perlu token:

```bash
curl http://localhost:8080/health
```

---

## Notes

* Warga hanya dapat mengakses laporan yang mereka miliki.
* Operator dapat melihat seluruh laporan serta memperbarui status laporan.
* RabbitMQ digunakan sebagai media komunikasi antar-microservice.
* Proses autentikasi menggunakan JWT yang diterbitkan oleh **Auth Service**, tapi divalidasi **secara lokal** oleh Citizen Service (bukan lewat introspection ke Auth Service).
* Pembuatan laporan tidak bergantung pada ketersediaan RabbitMQ sehingga laporan tetap tersimpan meskipun proses publish event gagal.
* Akun baru wajib memanggil `POST /api/citizens/register` sekali sebelum bisa memakai endpoint profile/reports/notifications lainnya — kalau dilewatkan, semuanya akan balas `404`.
