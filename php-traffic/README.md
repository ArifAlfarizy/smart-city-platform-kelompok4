# Smart City Platform — Traffic & Incident Service

Layanan **Traffic & Incident Service** berbasis **PHP 8.2 Monolithic/MVC Layer** yang bertanggung jawab penuh untuk mengelola pencatatan data sensor lalu lintas otomatis (*IoT Gateway/Node-RED*) serta manajemen pelaporan insiden jalan raya secara manual oleh Operator Kota.

Layanan ini terintegrasi secara asinkron dengan **Python ML Service** melalui message broker RabbitMQ untuk memicu kalkulasi prediksi kepadatan kota secara real-time.

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
│   ├── Models/           # Query SQL aman (Prepared Statements)
│   │   ├── Incident.php
│   │   └── TrafficData.php
│   ├── Services/         # Integrasi Pihak Ketiga (Asinkron)
│   │   └── RabbitMQPublisher.php
│   └── Database.php      # Singleton Connection Wrapper (PDO)
├── public/
│   └── index.php         # Front Controller / Routing Gate Utama
├── vendor/               # Dependensi Composer (php-amqplib)
├── .env                  # Konfigurasi Lokal Kredensial (Git Ignored)
└── README.md             # Dokumentasi Layanan

---

## Endpoint
1. GET /api/traffic/health
2. GET /api/traffic/current
3. GET /api/traffic/zones/{zone}
4. POST /api/traffic/incidents
5. GET /api/traffic/incidents
6. PUT /api/traffic/incidents/{id}