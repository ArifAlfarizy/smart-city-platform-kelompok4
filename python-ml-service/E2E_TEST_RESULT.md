# Hasil Testing End-to-End — ML Service

## Skenario S1 — IoT Data Ingestion
- **Status:** Berhasil
- **Bukti:** Consumer menerima event traffic dari RabbitMQ, prediksi dijalankan
- **Output:** Zona A (95 kend)  Padat, Zona B (30 kend)  Lancar

## Skenario S3 — ML Real-time Prediction
- **Status:** Berhasil
- **Endpoint tested:**
  - POST /api/ml/predict/traffic  200 OK, predicted_density: xx.x
  - POST /api/ml/predict/aqi  200 OK, aqi_category: Unhealthy
  - GET /api/ml/model/feature-importance  200 OK
- **Response time:** < 500ms

## Skenario S6 — Anomaly Alert Flow
- **Status:**  Berhasil
- **Bukti:** Nilai sensor ekstrem (999.9)  is_anomaly: true  ml.anomaly.detected
  ter-publish ke RabbitMQ  subscriber menerima alert
- **Severity:** Kritis

## Catatan
- Test dilakukan di environment lokal (bukan server)
- RabbitMQ: Docker container lokal
- ML Service: uvicorn lokal port 5000
- Database: tidak digunakan (graceful skip)