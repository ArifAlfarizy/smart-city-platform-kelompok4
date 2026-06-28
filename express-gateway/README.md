# API Gateway

Single entry point untuk semua client (web/mobile/IoT) ke Smart City Platform. Bertugas: verifikasi JWT, rate limiting, CORS, proxy ke service backend yang sesuai, dan agregasi data untuk endpoint dashboard.

## Daftar Isi

- [Tech Stack](#tech-stack)
- [Struktur Folder](#struktur-folder)
- [Environment Variables](#environment-variables)
- [Instalasi](#instalasi)
- [Menjalankan Service](#menjalankan-service)
- [Routing & Middleware](#routing--middleware)
- [Cara Kerja Verifikasi Token](#cara-kerja-verifikasi-token)
- [Endpoint Dashboard](#endpoint-dashboard)
- [Catatan Teknis](#catatan-teknis)

## Tech Stack

- Node.js 18 (Alpine)
- Express 5
- `http-proxy-middleware` — proxy ke service backend
- `jsonwebtoken` — verifikasi JWT secara lokal
- `express-rate-limit` — rate limiting
- ES Modules (`"type": "module"`)

## Struktur Folder

```
express-gateway/
├── Dockerfile
├── package.json
├── .env.example
└── src/
    ├── index.js                   # entry point, daftar route & urutan middleware
    ├── config/
    │   └── services.js            # base URL tiap service backend + path dashboard
    ├── middleware/
    │   ├── cors.js
    │   ├── logger.js              # request logging (JSON, ke stdout)
    │   ├── rateLimit.js           # limiter umum & limiter khusus auth
    │   ├── requireRole.js         # guard berbasis role (tersedia, belum dipasang di index.js)
    │   ├── serviceProxy.js        # factory proxy + forward header X-User-*
    │   └── verifyToken.js         # verifikasi JWT lokal + fallback introspection
    └── routes/
        └── dashboard.js           # agregator data dari 4 service sekaligus
```

## Environment Variables

> **Perhatian:** `express-gateway/.env.example` di repo ini **kosong**. Kamu wajib membuat `.env` sendiri (atau mengandalkan environment variables dari `docker-compose.yml` kalau menjalankan lewat Docker). Berikut semua variable yang benar-benar dibaca oleh kode:

| Variable | Wajib | Default kalau kosong | Dipakai di |
|---|---|---|---|
| `PORT` | — | `3000` | `config/services.js` |
| `AUTH_SERVICE_URL` | ✅ | `http://localhost:3001` | proxy `/auth/*`, `/oauth/*`, fallback introspection di `verifyToken.js` |
| `TRAFFIC_SERVICE_URL` | ✅ | `http://localhost:8001` | proxy `/api/traffic*` |
| `ENVIRONMENT_SERVICE_URL` | ✅ | `http://localhost:8002` | proxy `/api/environment` |
| `CITIZEN_SERVICE_URL` | ✅ | `http://localhost:8080` | proxy `/api/citizens` |
| `ML_SERVICE_URL` | ✅ | `http://localhost:5000` | proxy `/api/ml` |
| `JWT_ACCESS_SECRET` | ✅ | — (kosong = semua verifikasi lokal gagal, selalu fallback ke introspection) | `verifyToken.js` |
| `TRAFFIC_SUMMARY_PATH` | — | `/api/traffic/current` | `dashboard.js` |
| `ENVIRONMENT_STATUS_PATH` | — | `/api/environment/current` | `dashboard.js` |
| `ENVIRONMENT_FLOOD_PATH` | — | `/api/environment/alerts` | `dashboard.js` |
| `CITIZEN_INCIDENTS_PATH` | — | `/api/citizens/reports/all` | `dashboard.js` |
| `DASHBOARD_FETCH_TIMEOUT_MS` | — | `5000` | timeout fetch paralel di `dashboard.js` |

Contoh `.env` untuk development standalone (semua service backend dianggap jalan di `localhost`):
```dotenv
PORT=3000
JWT_ACCESS_SECRET=ganti_dengan_secret_minimal_32_karakter
AUTH_SERVICE_URL=http://localhost:3002
TRAFFIC_SERVICE_URL=http://localhost:8001
ENVIRONMENT_SERVICE_URL=http://localhost:8002
CITIZEN_SERVICE_URL=http://localhost:8080
ML_SERVICE_URL=http://localhost:5001
TRAFFIC_SUMMARY_PATH=/api/traffic/current
ENVIRONMENT_STATUS_PATH=/api/environment/current
ENVIRONMENT_FLOOD_PATH=/api/environment/alerts
CITIZEN_INCIDENTS_PATH=/api/citizens/reports/all
DASHBOARD_FETCH_TIMEOUT_MS=5000
```

> Saat jalan via Docker Compose (root project), kamu **tidak perlu** bikin `.env` manual — semua variable di atas (kecuali path dashboard) sudah di-set langsung di `docker-compose.yml` dengan nama service container sebagai host (contoh: `http://auth-service:3002`).

## Instalasi

### Standalone (tanpa Docker)

```bash
cd express-gateway
npm install
cp .env.example .env   # file kosong, isi manual sesuai tabel di atas
```

### Via Docker

```bash
cd express-gateway
docker build -t api-gateway .
```

## Menjalankan Service

### Standalone

Pastikan semua service backend yang ditunjuk `*_SERVICE_URL` di `.env` sudah jalan dulu (auth-service minimal, karena dipakai untuk fallback introspection).

```bash
node src/index.js
```

### Via Docker Compose (dari root project)

```bash
docker compose up -d --build api-gateway
```

Verifikasi:
```bash
curl http://localhost:3000/health
```
```json
{ "status": "success", "service": "api-gateway", "timestamp": "2026-06-27T12:00:00.000Z" }
```

## Routing & Middleware

Urutan middleware global: `cors` → `requestLogger`, lalu tiap route punya kombinasi middleware sendiri sebelum proxy.

| Route | Middleware | Proxy ke | Publik/Protected |
|---|---|---|---|
| `GET /health` | — | (internal) | Publik |
| `POST /auth/register` | `authLimiter` | `services.auth` | Publik |
| `POST /auth/login` | `authLimiter` | `services.auth` | Publik |
| `POST /oauth/token` | `authLimiter` | `services.auth` | Publik |
| `POST /oauth/revoke` | `authLimiter` | `services.auth` | Publik |
| `POST /oauth/introspect` | `authLimiter` | `services.auth` | Publik |
| `/api/traffic*` (5 varian path) | `limiter`, `verifyToken` | `services.traffic` | Protected |
| `/api/environment` | `limiter`, `verifyToken` | `services.environment` | Protected |
| `/api/citizens` | `limiter`, `verifyToken` | `services.citizen` | Protected |
| `/api/ml` | `limiter`, `verifyToken` | `services.ml` | Protected |
| `GET /api/dashboard` | `limiter`, `verifyToken` | (agregasi, lihat di bawah) | Protected |
| *lainnya* | — | — | `404` JSON |

Rate limit:
- `authLimiter`: 100 request / jam per IP, khusus endpoint auth.
- `limiter`: 100 request / 15 menit per IP, untuk semua endpoint protected.

Saat request berhasil lolos `verifyToken`, gateway menambahkan header berikut sebelum diteruskan ke service backend (lihat `serviceProxy.js`):
```
X-User-Id: <id>
X-User-Role: <role>
X-User-Email: <email>
X-Client-Id: <client_id>   (kalau token berasal dari grant client_credentials)
```
Service backend bisa membaca header ini untuk tahu siapa yang request, tanpa perlu decode JWT sendiri.

## Cara Kerja Verifikasi Token

`middleware/verifyToken.js` punya 2 lapis:

1. **Verifikasi lokal** — `jwt.verify(token, JWT_ACCESS_SECRET)`. Kalau berhasil, payload langsung dipakai sebagai `req.user`, tanpa round-trip ke service lain (cepat).
2. **Fallback introspection** — kalau verifikasi lokal gagal (signature tidak cocok, token bukan JWT, dll), gateway memanggil `POST {AUTH_SERVICE_URL}/oauth/introspect` untuk minta auth-service memvalidasi. Ini juga jalur yang dipakai untuk refresh token atau token dari client lain yang secretnya berbeda.

Implikasi: kalau `JWT_ACCESS_SECRET` di gateway **tidak sama** dengan yang dipakai auth-service untuk sign token, verifikasi lokal akan **selalu** gagal dan setiap request protected akan selalu menambah 1 round-trip HTTP ke auth-service. Tetap berfungsi, tapi lebih lambat — pastikan secret-nya identik di kedua service untuk performa optimal.

Error response token:
| Kondisi | Status | Body |
|---|---|---|
| Header `Authorization` tidak ada | `401` | `{ "error": "Access denied. No token provided." }` |
| Format token salah (bukan `Bearer <token>`) | `401` | `{ "error": "Access denied. Invalid token format" }` |
| Token expired | `401` | `{ "error": "Token expired. Please login again." }` |
| Token invalid (gagal lokal & introspection) | `401` | `{ "error": "Invalid or expired token." }` |

## Endpoint Dashboard

`GET /api/dashboard` memanggil 4 service backend secara **paralel** (`Promise.all`) dan menggabungkan hasilnya:

```bash
curl http://localhost:3000/api/dashboard \
  -H "Authorization: Bearer <access-token>"
```

```json
{
  "success": true,
  "partial": false,
  "message": "Dashboard data berhasil diambil dari semua service.",
  "generated_at": "2026-06-27T12:00:00.000Z",
  "data": {
    "traffic": { "...": "dari TRAFFIC_SUMMARY_PATH" },
    "environment": { "...": "dari ENVIRONMENT_STATUS_PATH" },
    "flood": { "...": "dari ENVIRONMENT_FLOOD_PATH" },
    "incidents": { "...": "dari CITIZEN_INCIDENTS_PATH" }
  }
}
```

Kalau salah satu service gagal/timeout, `partial` jadi `true` dan field yang gagal berisi `{ "error": "..." }` — request tetap balas `200`, tidak ada service yang menggagalkan keseluruhan response (graceful degradation). Timeout per panggilan diatur lewat `DASHBOARD_FETCH_TIMEOUT_MS`.

## Catatan Teknis

- `middleware/requireRole.js` sudah tersedia (guard berbasis `req.user.role`), tapi **belum dipasang** di route manapun di `index.js` saat ini. Kalau perlu membatasi endpoint tertentu ke role spesifik (misal `operator` saja), pasang seperti:
  ```js
  app.use("/api/traffic-admin", limiter, verifyToken, requireRole("operator"), makeProxy(services.traffic));
  ```
- `cors.js` saat ini mengizinkan `Access-Control-Allow-Origin: *` — cocok untuk development, **pertimbangkan dipersempit** untuk production.
- Proxy error (service tujuan down/unreachable) ditangani `serviceProxy.js`, balas `502` dengan body `{ "success": false, "message": "Service tidak dapat dihubungi: <url>" }` — cek `docker compose logs <service-tujuan>` kalau ini terjadi.