USE smartcity;

-- Seed Citizen Service
INSERT INTO citizens
(user_id, nik, name, phone) 
VALUES
(1, '3141111111111111', 'Jane Doe', '081111111111'),
(2, '3152222222222222', 'John Doe', '082222222222'),
(3, '3163333333333333', 'Joe Public', '083333333333');

INSERT INTO reports
(citizen_id, road_name, category, description, status)
VALUES
(1, 'Jalan MT Haryono', 'accident', 'Terjadi kecelakaan antara dua kendaraan di dekat Simpang Cawang.', 'process'),
(2, 'Jalan MT Haryono', 'flood', 'Genangan air sekitar 20 cm menyebabkan kendaraan melambat.', 'pending'),
(3, 'Jalan Prof Dr Soepomo', 'broken_vehicle', 'Terdapat mobil mogok di bahu jalan sehingga menghambat arus lalu lintas.', 'completed');

INSERT INTO notifications
(citizen_id, title, message, is_read)
VALUES
(1, 'Laporan Diproses', 'Operator telah menerima laporan dan sedang melakukan verifikasi.', FALSE),
(2, 'Petugas Dikirim', 'Petugas telah dikirim ke lokasi untuk melakukan penanganan.', FALSE),
(3, 'Penanganan Selesai', 'Penanganan insiden telah selesai dilakukan dan kondisi lalu lintas kembali normal.', TRUE);

-- Seed Data untuk traffic_data
INSERT INTO traffic_data (road_name, vehicle_count, average_speed, congestion_level, observation_time) VALUES
('Jalan MT Haryono', 150, 12.50, 'Macet', '2026-06-03 20:00:00'),
('Jalan MT Haryono', 45, 45.20, 'Normal', '2026-06-03 20:05:00'),
('Jalan MT Haryono', 95, 28.00, 'Padat', '2026-06-03 20:10:00'),
('Jalan MT Haryono', 165, 10.15, 'Sangat Macet', '2026-06-03 20:15:00'),
('Jalan MT Haryono', 50, 42.00, 'Normal', '2026-06-03 20:20:00');

-- Seed Data untuk incidents
INSERT INTO incidents (road_name, incident_type, description, status, created_at) VALUES
('Jalan MT Haryono', 'accident', 'Tabrakan motor di depan pasar, menutup lajur kiri.', 'active', '2026-06-03 20:00:00'),
('Jalan MT Haryono', 'fallen_tree', 'Pohon tumbang menghalangi jalan utama zona C.', 'resolved', '2026-06-03 15:30:00');

INSERT IGNORE INTO zones (id, name, district, area_km2) VALUES
    (1, 'A', 'Jakarta Pusat',   48.13),
    (2, 'B', 'Jakarta Selatan', 141.27),
    (3, 'C', 'Jakarta Utara',   146.66),
    (4, 'D', 'Jakarta Barat',   129.54),
    (5, 'E', 'Jakarta Timur',   187.73);
