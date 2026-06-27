# Auth Service

Service otentikasi & otorisasi bergaya **OAuth 2.0** untuk Smart City Platform. Menangani registrasi user, login, penerbitan access/refresh token (JWT), revoke, dan token introspection untuk dipakai service lain (lewat API Gateway).

## Daftar Isi

- [Tech Stack](#tech-stack)
- [Struktur Folder](#struktur-folder)
- [Environment Variables](#environment-variables)
- [Instalasi](#instalasi)
- [Menjalankan Service](#menjalankan-service)
- [Database](#database)
- [API Endpoints](#api-endpoints)
- [Catatan Teknis](#catatan-teknis)

## Tech Stack

- Node.js 18 (Alpine)
- Express 5
- `mysql2` (promise pool) — koneksi ke MySQL
- `jsonwebtoken` — penerbitan & verifikasi JWT
- `bcrypt` — hashing password
- `cookie-parser`
- ES Modules (`"type": "module"` di `package.json`)

## Struktur Folder

```
auth-service/
├── Dockerfile
├── package.json
├── .env.example
├── src/
│   ├── index.js                  # entry point, route mounting, health check
│   ├── configs/
│   │   └── db.js                 # MySQL connection pool
│   ├── controllers/
│   │   ├── authController.js     # register
│   │   └── oauthController.js    # token (semua grant type), revoke, introspect
│   ├── models/
│   │   ├── userModel.js
│   │   └── tokenModel.js
│   ├── routes/
│   │   ├── authRouter.js         # /auth/*
│   │   └── oauthRouter.js        # /oauth/*
│   └── utils/
│       └── tokenHelper.js        # generate access token / client token
└── test/
    └── test_gateway_introspect.js
```

## Environment Variables

Isi `.env` (copy dari `.env.example`):

| Variable | Wajib | Keterangan |
|---|---|---|
| `PORT` | ✅ | Port HTTP service (default project: `3002`) |
| `DB_HOST` | ✅ | Host MySQL (`mysql_db` kalau via Docker Compose, `localhost` kalau standalone) |
| `DB_PORT` | ✅ | Port MySQL, default `3306` |
| `DB_USER` | ✅ | User MySQL |
| `DB_PASSWORD` | ✅ | Password MySQL |
| `DB_NAME` | ✅ | Nama database, `smartcity` |
| `SALT_ROUNDS` | ✅ | Jumlah salt round untuk `bcrypt` (rekomendasi: `10`) |
| `JWT_ACCESS_SECRET` | ✅ | Secret untuk sign/verify JWT — **harus identik** dengan secret yang dipakai `express-gateway` dan service lain yang memvalidasi token |
| `JWT_REFRESH_SECRET` | — | Disediakan di `.env.example`, tapi refresh token saat ini disimpan sebagai random hash di DB (bukan JWT), jadi variable ini belum dipakai di kode |
| `JWT_ACCESS_EXPIRES_IN` | ✅ | Masa berlaku access token dalam detik (contoh: `86400` = 24 jam) |

Contoh `.env`:
```dotenv
PORT=3002
DB_HOST=mysql_db
DB_PORT=3306
DB_USER=env_user
DB_PASSWORD=env_secret
DB_NAME=smartcity
SALT_ROUNDS=10
JWT_ACCESS_SECRET=ganti_dengan_secret_minimal_32_karakter
JWT_REFRESH_SECRET=ganti_dengan_secret_lain
JWT_ACCESS_EXPIRES_IN=86400
```

## Instalasi

### Standalone (tanpa Docker)

Butuh Node.js 18+ dan instance MySQL yang sudah jalan (lihat `database/schema.sql` di root project untuk struktur tabel).

```bash
cd auth-service
npm install
cp .env.example .env   # lalu isi sesuai tabel di atas
```

### Via Docker (rekomendasi)

Service ini didesain untuk jalan sebagai bagian dari `docker-compose.yml` di root project, tapi bisa juga di-build sendiri:

```bash
cd auth-service
docker build -t auth-service .
```

## Menjalankan Service

### Standalone

```bash
node src/index.js
```

Service connect ke MySQL saat startup (`startServer()` di `index.js`) — kalau koneksi gagal, proses langsung `exit(1)` dengan pesan error di console. Pastikan MySQL sudah jalan dan kredensial di `.env` benar sebelum start.

### Via Docker Compose (dari root project)

```bash
docker compose up -d --build auth-service
```

Cek status & health:
```bash
docker compose ps auth-service
curl http://localhost:3002/health
```

Response sehat:
```json
{
  "status": "success",
  "service": "auth-service",
  "db_connected": true,
  "timeStamp": "2026-06-27T12:00:00.000Z"
}
```

## Database

Auth service memakai 4 tabel dari `database/schema.sql` (root project):

| Tabel | Dipakai untuk |
|---|---|
| `users` | Data akun (email, password hash, role) |
| `oauth_clients` | Client credentials untuk grant `client_credentials` (service-to-service) |
| `refresh_tokens` | Refresh token (disimpan sebagai SHA-256 hash, bukan plaintext) |
| `revoked_tokens` | Daftar JWT yang sudah di-revoke (dicek lewat `jti` saat introspection) |

## API Endpoints

Base URL standalone: `http://localhost:3002` — kalau lewat gateway, prefix tetap sama (`/auth/*`, `/oauth/*`) di `http://localhost:3000`.

### `POST /auth/register`

Registrasi user baru.

```bash
curl -X POST http://localhost:3002/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "password123",
    "role": "citizen"
  }'
```

`name`, `email`, `password` wajib. `role` opsional (default `citizen`).

### `POST /auth/login`

Shortcut untuk grant `password` — secara internal memanggil handler `/oauth/token` dengan `grant_type: "password"` otomatis ditambahkan.

```bash
curl -X POST http://localhost:3002/auth/login \
  -H "Content-Type: application/json" \
  -d '{ "email": "jane@example.com", "password": "password123" }'
```

Response:
```json
{
  "success": true,
  "access_token": "<jwt>",
  "refresh_token": "<random-hex>",
  "token_type": "Bearer",
  "expires_in": 86400
}
```

### `POST /oauth/token`

Endpoint OAuth generik, mendukung 3 `grant_type`:

| `grant_type` | Body tambahan | Keterangan |
|---|---|---|
| `password` | `email`, `password` | Sama seperti `/auth/login` |
| `refresh_token` | `refresh_token` | Tukar refresh token lama (otomatis di-revoke) dengan pasangan token baru |
| `client_credentials` | `client_id`, `client_secret` | Untuk komunikasi antar service, perlu row di tabel `oauth_clients` dengan `grant_types` yang mengandung `client_credentials` |

```bash
curl -X POST http://localhost:3002/oauth/token \
  -H "Content-Type: application/json" \
  -d '{ "grant_type": "refresh_token", "refresh_token": "<refresh-token-lama>" }'
```

### `POST /oauth/revoke`

Revoke refresh token. Selalu balas `200` (by design — tidak membocorkan apakah token valid atau tidak).

```bash
curl -X POST http://localhost:3002/oauth/revoke \
  -H "Content-Type: application/json" \
  -d '{ "refresh_token": "<refresh-token>" }'
```

### `POST /oauth/introspect`

Dipakai gateway/service lain untuk validasi token (fallback kalau verifikasi JWT lokal gagal, atau untuk cek status revoke). Mendukung access token (JWT) maupun refresh token (hash di DB).

```bash
curl -X POST http://localhost:3002/oauth/introspect \
  -H "Content-Type: application/json" \
  -d '{ "token": "<access-or-refresh-token>" }'
```

Response (token aktif):
```json
{
  "active": true,
  "client_id": "unknown",
  "user_id": 1,
  "email": "jane@example.com",
  "role": "citizen",
  "token_type": "access_token",
  "exp": 1772200000,
  "iat": 1772113600,
  "scope": "basic",
  "sub": "jane@example.com"
}
```

Response (token tidak aktif/invalid): `{ "active": false }`

### `GET /health`

Health check, mengecek koneksi DB secara live (`SELECT 1`).

## Catatan Teknis

- **Refresh token tidak disimpan sebagai JWT** — hanya random bytes (`crypto.randomBytes(64)`) yang di-hash SHA-256 sebelum disimpan ke `refresh_tokens`. `JWT_REFRESH_SECRET` di `.env.example` saat ini tidak dipakai di kode manapun.
- **`introspect`** adalah jalur fallback penting untuk service lain (terutama `express-gateway`) — kalau `JWT_ACCESS_SECRET` di service pemanggil berbeda dari yang dipakai auth-service untuk sign token, verifikasi lokal akan selalu gagal dan **selalu** jatuh ke introspection (menambah latency, tapi tetap berfungsi).
- Untuk testing end-to-end alur OAuth (register → login → introspect access & refresh token → cek token invalid), jalankan:
  ```bash
  node test/test_gateway_introspect.js
  ```
  Catatan: script ini hardcode `BASE_URL = http://localhost:3001`, sesuaikan dulu ke port aktual (`3002`) sebelum dijalankan.