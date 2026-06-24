USE smartcity;

-- Seed Citizen Service
INSERT INTO citizens
(user_id, nik, name, zone, phone)
VALUES
(1,'3141111111111111','Jane Doe','081111111111'),
(2,'3152222222222222','John Doe','082222222222'),
(3,'3163333333333333','Joe Public','083333333333');

INSERT INTO reports
(citizen_id, road_name, category, description, status)
VALUES
(1, 'Jalan MT Haryono', 'accident', 'Terjadi kecelakaan antara dua kendaraan di dekat Simpang Cawang.', 'process'),
(2, 'Jalan MT Haryono', 'flood', 'Genangan air sekitar 20 cm menyebabkan kendaraan melambat.', 'pending'),
(3, 'Jalan Prof Dr Soepomo', 'broken_vehicle', 'Terdapat mobil mogok di bahu jalan sehingga menghambat arus lalu lintas.', 'completed' );

INSERT INTO notifications
(citizen_id, title, message, is_read)
VALUES
(1, 'Laporan Diproses', 'Operator telah menerima laporan dan sedang melakukan verifikasi.', FALSE),
(2, 'Petugas Dikirim', 'Petugas telah dikirim ke lokasi untuk melakukan penanganan.', FALSE ),
(3, 'Penanganan Selesai', 'Penanganan insiden telah selesai dilakukan dan kondisi lalu lintas kembali normal.', TRUE);


-- Seed Data untuk traffic_data
INSERT INTO traffic_data (sensor_id, zone, vehicle_count, avg_speed, congestion_level, recorded_at) VALUES
('TS001', 'A', 150, 12.50, 8, '2026-06-03 20:00:00'),
('TS002', 'B', 45, 45.20, 2, '2026-06-03 20:05:00'),
('TS003', 'C', 95, 28.00, 5, '2026-06-03 20:10:00'),
('TS001', 'A', 165, 10.15, 9, '2026-06-03 20:15:00'),
('TS002', 'B', 50, 42.00, 3, '2026-06-03 20:20:00');

-- Seed Data untuk incidents
INSERT INTO incidents (zone, incident_type, description, status, created_at) VALUES
('A', 'Kecelakaan', 'Tabrakan motor di depan pasar, menutup lajur kiri.', 'active', '2026-06-03 20:00:00'),
('C', 'Pohon Tumbang', 'Pohon tumbang menghalangi jalan utama zona C.', 'resolved', '2026-06-03 15:30:00');

INSERT IGNORE INTO zones (id, name, district, area_km2) VALUES
    (1, 'A', 'Jakarta Pusat',   48.13),
    (2, 'B', 'Jakarta Selatan', 141.27),
    (3, 'C', 'Jakarta Utara',   146.66),
    (4, 'D', 'Jakarta Barat',   129.54),
    (5, 'E', 'Jakarta Timur',   187.73);

INSERT INTO environment_data
    (sensor_id, zone, aqi, aqi_status, temperature, humidity,
     rain_level, rain_intensity, rain_status,
     flood_level, flood_status, pm25, pm10, no2, co, o3, recorded_at)
VALUES
('ESP32-A-01','A', 78.2,'Sedang',              30.5, 68.0,  0.0,  0.0, 'Tidak Hujan', 3.0, 'Aman',   32.1,44.5,38.2,1.1,52.0, DATE_SUB(NOW(), INTERVAL 5  MINUTE)),
('ESP32-A-01','A', 85.5,'Sedang',              31.2, 72.1,  5.0,  2.1, 'Tidak Hujan', 5.0, 'Aman',   35.2,48.1,42.3,1.2,55.0, DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
('ESP32-A-01','A', 92.0,'Sedang',              32.0, 74.5, 10.0,  8.5, 'Hujan Ringan',6.5, 'Aman',   40.1,55.0,45.0,1.5,60.0, DATE_SUB(NOW(), INTERVAL 65 MINUTE)),
('ESP32-B-01','B',110.3,'Tidak Sehat (Sensitif)',33.5,80.0,65.0, 45.2,'Hujan Lebat', 52.0,'Bahaya',  55.0,72.0,62.0,2.1,70.0, DATE_SUB(NOW(), INTERVAL 4  MINUTE)),
('ESP32-B-01','B',125.7,'Tidak Sehat (Sensitif)',34.0,85.0,80.0, 68.3,'Hujan Lebat', 61.0,'Bahaya',  62.5,82.0,70.0,2.5,78.0, DATE_SUB(NOW(), INTERVAL 34 MINUTE)),
('ESP32-B-01','B', 75.2,'Sedang',              30.0, 68.3,  2.0,  1.0, 'Tidak Hujan',10.0,'Aman',    28.3,39.5,35.0,0.9,45.0, DATE_SUB(NOW(), INTERVAL 94 MINUTE)),
('ESP32-C-01','C',130.7,'Tidak Sehat',         35.0, 88.0, 80.0, 72.3,'Hujan Lebat', 75.0,'Bahaya',  68.0,90.0,80.0,3.0,95.0, DATE_SUB(NOW(), INTERVAL 3  MINUTE)),
('ESP32-C-01','C', 60.1,'Sedang',              29.5, 65.0,  0.0,  0.0,'Tidak Hujan',  3.0,'Aman',    22.1,30.5,28.0,0.7,38.0, DATE_SUB(NOW(), INTERVAL 63 MINUTE)),
('ESP32-D-01','D', 95.0,'Sedang',              31.8, 75.5, 20.0, 15.0,'Hujan Sedang',20.0,'Waspada', 42.0,58.0,48.0,1.6,62.0, DATE_SUB(NOW(), INTERVAL 6  MINUTE)),
('ESP32-D-01','D', 88.3,'Sedang',              31.0, 73.0, 15.0, 10.5,'Hujan Ringan',15.0,'Waspada', 37.5,51.0,44.0,1.3,58.0, DATE_SUB(NOW(), INTERVAL 36 MINUTE)),
('ESP32-E-01','E', 55.0,'Sedang',              28.5, 60.0,  0.0,  0.0,'Tidak Hujan',  0.0,'Aman',    18.0,25.0,22.0,0.5,30.0, DATE_SUB(NOW(), INTERVAL 7  MINUTE)),
('ESP32-E-01','E', 62.4,'Sedang',              29.0, 62.0,  3.0,  1.5,'Tidak Hujan',  1.0,'Aman',    24.0,33.0,29.0,0.8,40.0, DATE_SUB(NOW(), INTERVAL 37 MINUTE));


INSERT INTO vehicle_counts
    (sensor_id, zone, vehicle_count, interval_sec, traffic_status,
     rain_intensity, rain_status, flood_level, recorded_at)
VALUES
('ESP32-A-01','A', 42, 60,'Macet',   0.0, 'Tidak Hujan', 3.0,  DATE_SUB(NOW(), INTERVAL 5  MINUTE)),
('ESP32-A-01','A', 38, 60,'Padat',   0.0, 'Tidak Hujan', 3.0,  DATE_SUB(NOW(), INTERVAL 65 MINUTE)),
('ESP32-A-01','A', 31, 60,'Padat',   2.1, 'Tidak Hujan', 5.0,  DATE_SUB(NOW(), INTERVAL 125 MINUTE)),
('ESP32-A-01','A', 22, 60,'Sedang',  8.5, 'Hujan Ringan',6.5,  DATE_SUB(NOW(), INTERVAL 185 MINUTE)),
('ESP32-A-01','A', 18, 60,'Sedang',  0.0, 'Tidak Hujan', 4.0,  DATE_SUB(NOW(), INTERVAL 245 MINUTE)),

('ESP32-B-01','B', 8,  60,'Sedang',  45.2,'Hujan Lebat', 52.0, DATE_SUB(NOW(), INTERVAL 4  MINUTE)),
('ESP32-B-01','B', 5,  60,'Lancar',  68.3,'Hujan Lebat', 61.0, DATE_SUB(NOW(), INTERVAL 64 MINUTE)),
('ESP32-B-01','B', 35, 60,'Padat',   1.0, 'Tidak Hujan', 10.0, DATE_SUB(NOW(), INTERVAL 184 MINUTE)),
('ESP32-B-01','B', 44, 60,'Macet',   0.0, 'Tidak Hujan', 8.0,  DATE_SUB(NOW(), INTERVAL 244 MINUTE)),
('ESP32-B-01','B', 12, 60,'Sedang',  22.5,'Hujan Sedang',30.0, DATE_SUB(NOW(), INTERVAL 124 MINUTE)),

('ESP32-C-01','C', 3,  60,'Lancar',  72.3,'Hujan Lebat', 75.0, DATE_SUB(NOW(), INTERVAL 3  MINUTE)),
('ESP32-C-01','C', 2,  60,'Lancar',  80.1,'Hujan Lebat', 80.0, DATE_SUB(NOW(), INTERVAL 63 MINUTE)),
('ESP32-C-01','C', 28, 60,'Padat',   0.0, 'Tidak Hujan', 3.0,  DATE_SUB(NOW(), INTERVAL 183 MINUTE)),
('ESP32-C-01','C', 19, 60,'Sedang',  5.5, 'Hujan Ringan',5.0,  DATE_SUB(NOW(), INTERVAL 123 MINUTE)),

('ESP32-D-01','D', 20, 60,'Sedang',  15.0,'Hujan Sedang',20.0, DATE_SUB(NOW(), INTERVAL 6  MINUTE)),
('ESP32-D-01','D', 25, 60,'Padat',   10.5,'Hujan Ringan',15.0, DATE_SUB(NOW(), INTERVAL 66 MINUTE)),
('ESP32-D-01','D', 33, 60,'Padat',   0.0, 'Tidak Hujan', 8.0,  DATE_SUB(NOW(), INTERVAL 126 MINUTE)),
('ESP32-D-01','D', 40, 60,'Macet',   0.0, 'Tidak Hujan', 5.0,  DATE_SUB(NOW(), INTERVAL 246 MINUTE)),

('ESP32-E-01','E', 25, 60,'Padat',   0.0, 'Tidak Hujan', 0.0,  DATE_SUB(NOW(), INTERVAL 7  MINUTE)),
('ESP32-E-01','E', 28, 60,'Padat',   1.5, 'Tidak Hujan', 1.0,  DATE_SUB(NOW(), INTERVAL 67 MINUTE)),
('ESP32-E-01','E', 15, 60,'Sedang',  0.0, 'Tidak Hujan', 0.0,  DATE_SUB(NOW(), INTERVAL 127 MINUTE)),
('ESP32-E-01','E', 10, 60,'Sedang',  0.0, 'Tidak Hujan', 0.0,  DATE_SUB(NOW(), INTERVAL 187 MINUTE));

INSERT INTO environment_alerts (zone, alert_type, value, threshold, message, severity, status) VALUES
    ('B', 'AQI_HIGH',   '110.3', '100', 'AQI melebihi batas aman di Zona B. Warga disarankan menggunakan masker.', 'WARNING',  'active'),
    ('B', 'FLOOD_HIGH', '52.0',  '50',  'Ketinggian air mencapai 52 cm di Zona B. Potensi banjir tinggi.',         'CRITICAL', 'active'),
    ('C', 'AQI_HIGH',   '130.7', '100', 'AQI sangat buruk di Zona C. Hindari aktivitas luar ruangan.',             'CRITICAL', 'active'),
    ('B', 'RAIN_HEAVY', '45.2',  '30',  'Intensitas hujan sangat tinggi di Zona B. Waspadai genangan.',            'WARNING',  'active');