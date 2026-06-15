# smart-city-platform-kelompok4

jalanin migrasi

## API Endpoints

| Method | Endpoint | Keterangan |
|--------|----------|------------|
| GET | /health | Health check service |
| POST | /api/environment/sensor | Terima data sensor |
| GET | /api/environment/current | Data terkini semua zona |
| GET | /api/environment/current?zone=A | Data terkini per zona |
| GET | /api/environment/zones | Daftar zona |
| GET | /api/environment/zones/{zone} | Riwayat data zona |
| GET | /api/environment/alerts | Daftar alert aktif |
| GET | /api/environment/alerts/{id} | Detail alert |
| PUT | /api/environment/alerts/{id}/resolve | Resolve alert |

# Lihat data terkini semua zona
curl http://localhost:8002/api/environment/current

# Lihat alert aktif
curl http://localhost:8002/api/environment/alerts

# Resolve alert
curl -X PUT http://localhost:8002/api/environment/alerts/1/resolve