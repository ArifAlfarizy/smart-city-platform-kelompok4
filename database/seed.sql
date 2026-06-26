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