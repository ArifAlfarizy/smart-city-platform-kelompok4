# Citizen Service

Citizen Service merupakan sebuah microservice pada **Smart Traffic Decision Support System** yang bertanggung jawab dalam mengelola data warga (*citizen*) dan laporan insiden. Service ini memungkinkan warga untuk mengirimkan laporan insiden, mengelola profil, melihat riwayat laporan serta notifikasi, dan mengirimkan event ke RabbitMQ sebagai media komunikasi antar-microservice.

---

## Overview

### Features

* Melakukan autentikasi pengguna menggunakan JWT yang diterbitkan oleh **Auth Service**
* Melihat profil warga (*citizen profile*)
* Memperbarui profil warga
* Membuat laporan insiden
* Melihat laporan milik sendiri
* Melihat seluruh laporan (Operator)
* Memperbarui status laporan (Operator)
* Melihat notifikasi warga
* Mengirim event `incident.created` ke RabbitMQ ketika laporan berhasil dibuat

---

## Technology Stack

* CodeIgniter 4
* PHP 8+
* MySQL
* JWT Authentication
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

### Citizen

| Method | Endpoint                | Description                   |
| ------ | ----------------------- | ----------------------------- |
| GET    | `/api/citizens/profile` | Mengambil data profil warga   |
| PUT    | `/api/citizens/profile` | Memperbarui data profil warga |

### Reports

| Method | Endpoint                            | Description                                             |
| ------ | ----------------------------------- | ------------------------------------------------------- |
| POST   | `/api/citizens/reports`             | Membuat laporan insiden baru                            |
| GET    | `/api/citizens/reports`             | Mengambil seluruh laporan milik warga yang sedang login |
| GET    | `/api/citizens/reports/all`         | Mengambil seluruh laporan (khusus Operator)             |
| PUT    | `/api/citizens/reports/{id}/status` | Memperbarui status laporan (khusus Operator)            |

### Notifications

| Method | Endpoint                      | Description                       |
| ------ | ----------------------------- | --------------------------------- |
| GET    | `/api/citizens/notifications` | Mengambil daftar notifikasi warga |

---

## Request Examples

### Create Report

**POST** `/api/citizens/reports`

```json
{
  "road_name": "MT Haryono",
  "category": "accident",
  "description": "Kecelakaan di simpang Cawang"
}
```

### Update Profile

**PUT** `/api/citizens/profile`

```json
{
  "name": "Budi Santoso",
  "phone": "08123456789"
}
```

---

## Authentication

Seluruh endpoint menggunakan autentikasi JWT.

Sertakan token pada header request berikut:

```http
Authorization: Bearer <token>
```

JWT diperoleh melalui **Auth Service**.

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
  "description": "Kecelakaan di simpang Cawang"
}
```

Apabila RabbitMQ tidak tersedia, laporan tetap akan disimpan ke database. Hanya proses pengiriman event yang dilewati.

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

Kemudian sesuaikan konfigurasi berikut pada file `.env`:

* Konfigurasi database
* Konfigurasi JWT
* Konfigurasi RabbitMQ

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

---

## Notes

* Warga hanya dapat mengakses laporan yang mereka miliki.
* Operator dapat melihat seluruh laporan serta memperbarui status laporan.
* RabbitMQ digunakan sebagai media komunikasi antar-microservice.
* Proses autentikasi menggunakan JWT yang diterbitkan oleh **Auth Service**.
* Pembuatan laporan tidak bergantung pada ketersediaan RabbitMQ sehingga laporan tetap tersimpan meskipun proses publish event gagal.
