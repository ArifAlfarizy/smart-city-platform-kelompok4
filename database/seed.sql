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
TRUNCATE TABLE traffic_data;
INSERT INTO traffic_data (road_name, vehicle_count, average_speed, congestion_level, observation_time) VALUES
('Jalan MT Haryono', 150, 12.50, 'Macet', '2026-06-03 20:00:00'),
('Jalan MT Haryono', 45, 45.20, 'Normal', '2026-06-03 20:05:00'),
('Jalan MT Haryono', 95, 28.00, 'Padat', '2026-06-03 20:10:00'),
('Jalan MT Haryono', 165, 10.15, 'Sangat Macet', '2026-06-03 20:15:00'),
('Jalan MT Haryono', 50, 42.00, 'Normal', '2026-06-03 20:20:00'),
-- Data Tambahan Jam Sibuk Pagi (07:00 - 08:30)
('Jalan MT Haryono', 180, 8.50, 'Sangat Macet', '2026-06-04 07:00:00'),
('Gatot Subroto', 210, 9.20, 'Sangat Macet', '2026-06-04 07:15:00'),
('Cawang', 140, 15.00, 'Macet', '2026-06-04 07:30:00'),
('Jalan Raya Pasar Minggu', 110, 22.40, 'Padat', '2026-06-04 07:45:00'),
('Jalan Prof Dr Soepomo', 95, 26.10, 'Padat', '2026-06-04 08:00:00'),
('Jalan KH Abdullah Syafei', 130, 14.30, 'Macet', '2026-06-04 08:15:00'),
('Jalan Raya Kalibata', 85, 31.00, 'Normal', '2026-06-04 08:30:00'),
-- Data Jam Kerja Siang (11:00 - 13:00)
('Jalan MT Haryono', 60, 38.50, 'Normal', '2026-06-04 11:00:00'),
('Gatot Subroto', 75, 35.00, 'Normal', '2026-06-04 11:30:00'),
('Manggarai', 90, 24.50, 'Padat', '2026-06-04 12:00:00'),
('Cawang', 55, 40.00, 'Normal', '2026-06-04 12:30:00'),
('Jalan Prof Dr Soepomo', 65, 37.20, 'Normal', '2026-06-04 13:00:00'),
-- Data Jam Sibuk Sore/Malam Pulang Kerja (17:00 - 19:45)
('Jalan MT Haryono', 195, 7.80, 'Sangat Macet', '2026-06-04 17:00:00'),
('Gatot Subroto', 230, 6.50, 'Sangat Macet', '2026-06-04 17:15:00'),
('Cawang', 175, 11.20, 'Macet', '2026-06-04 17:30:00'),
('Jalan Raya Pasar Minggu', 150, 13.90, 'Macet', '2026-06-04 17:45:00'),
('Jalan Raya Kalibata', 120, 19.80, 'Padat', '2026-06-04 18:00:00'),
('Jalan KH Abdullah Syafei', 160, 10.50, 'Macet', '2026-06-04 18:15:00'),
('Manggarai', 105, 21.00, 'Padat', '2026-06-04 18:30:00'),
('Jalan Prof Dr Soepomo', 115, 18.30, 'Padat', '2026-06-04 18:45:00'),
('Jalan MT Haryono', 185, 9.00, 'Sangat Macet', '2026-06-04 19:00:00'),
('Gatot Subroto', 200, 8.10, 'Sangat Macet', '2026-06-04 19:15:00'),
('Cawang', 135, 16.40, 'Macet', '2026-06-04 19:30:00'),
('Jalan Raya Pasar Minggu', 90, 27.00, 'Padat', '2026-06-04 19:45:00'),
-- Data Malam Hari (21:00 - 23:30)
('Jalan MT Haryono', 40, 48.00, 'Normal', '2026-06-04 21:00:00'),
('Gatot Subroto', 50, 46.50, 'Normal', '2026-06-04 21:30:00'),
('Manggarai', 35, 42.00, 'Normal', '2026-06-04 22:00:00'),
('Cawang', 30, 50.00, 'Normal', '2026-06-04 22:30:00'),
('Jalan Raya Kalibata', 25, 52.30, 'Normal', '2026-06-04 23:00:00'),
('Jalan KH Abdullah Syafei', 20, 55.00, 'Normal', '2026-06-04 23:30:00'),
-- Data Dini Hari (01:00 - 05:00)
('Jalan MT Haryono', 15, 60.00, 'Normal', '2026-06-05 01:00:00'),
('Gatot Subroto', 10, 62.50, 'Normal', '2026-06-05 02:00:00'),
('Cawang', 8, 65.00, 'Normal', '2026-06-05 03:00:00'),
('Manggarai', 12, 58.00, 'Normal', '2026-06-05 04:00:00'),
('Jalan Prof Dr Soepomo', 22, 50.00, 'Normal', '2026-06-05 05:00:00');

-- Seed Data untuk incidents
TRUNCATE TABLE incidents;
INSERT INTO incidents (road_name, incident_type, description, status, created_at) VALUES
('Jalan MT Haryono', 'accident', 'Tabrakan motor di depan pasar, menutup lajur kiri.', 'active', '2026-06-03 20:00:00'),
('Jalan MT Haryono', 'fallen_tree', 'Pohon tumbang menghalangi jalan utama zona C.', 'resolved', '2026-06-03 15:30:00'),

('Gatot Subroto', 'broken_vehicle', 'Truk mogok di lajur tengah busway menyebabkan antrean panjang.', 'active', '2026-06-04 07:10:00'),
('Cawang', 'flood', 'Genangan air setinggi 30cm akibat luapan drainase setelah hujan deras.', 'active', '2026-06-04 07:25:00'),
('Jalan Raya Pasar Minggu', 'road_obstacle', 'Bahan material bangunan tumpah dari truk di tikungan jalan.', 'resolved', '2026-06-04 08:00:00'),
('Jalan KH Abdullah Syafei', 'traffic_light_damage', 'Lampu lalu lintas mati total berakibat pada simpang semrawut.', 'active', '2026-06-04 08:20:00'),
('Jalan Prof Dr Soepomo', 'accident', 'Tabrakan beruntun melibatkan tiga kendaraan roda empat.', 'resolved', '2026-06-04 09:15:00'),
('Manggarai', 'fallen_tree', 'Dahan pohon patah berukuran besar menutupi jalur pedestrian dan lajur kiri.', 'resolved', '2026-06-04 10:05:00'),
('Jalan Raya Kalibata', 'broken_vehicle', 'Bus TransJakarta mengalami gangguan mesin tepat di tanjakan flyover.', 'active', '2026-06-04 11:40:00'),
('Jalan MT Haryono', 'road_obstacle', 'Terdapat barrier pembatas jalan yang bergeser ke tengah lajur cepat.', 'resolved', '2026-06-04 12:15:00'),
('Gatot Subroto', 'accident', 'Senggolan antara angkot dan pengendara roda dua.', 'resolved', '2026-06-04 13:00:00'),
('Cawang', 'broken_vehicle', 'Mobil pribadi mengalami pecah ban di gerbang tol masuk.', 'resolved', '2026-06-04 14:10:00'),
('Jalan Raya Pasar Minggu', 'flood', 'Air menggenang setinggi 20cm pasca hujan lokal siang hari.', 'resolved', '2026-06-04 15:00:00'),
('Jalan KH Abdullah Syafei', 'road_obstacle', 'Pekerjaan perbaikan lubang jalan sementara memakan satu lajur jalan.', 'active', '2026-06-04 16:00:00'),
('Jalan MT Haryono', 'accident', 'Kecelakaan beruntun minibus di layang Pancoran arah timur.', 'active', '2026-06-04 17:05:00'),
('Gatot Subroto', 'broken_vehicle', 'Truk kontainer mogok sebelum flyover Kuningan pada jam pulang kantor.', 'active', '2026-06-04 17:30:00'),
('Cawang', 'traffic_light_damage', 'Lampu kuning berkedip (*flashing error*) menyebabkan kekacauan arus kendaraan.', 'active', '2026-06-04 17:50:00'),
('Jalan Prof Dr Soepomo', 'accident', 'Tabrakan sepeda motor logistik kurir makanan.', 'resolved', '2026-06-04 18:20:00'),
('Manggarai', 'road_obstacle', 'Tumpukan sampah plastik proyek menyumbat pembatas badan jalan.', 'resolved', '2026-06-04 19:00:00'),
('Jalan Raya Kalibata', 'accident', 'Pengendara motor terpeleset ceceran oli di tikungan bawah stasiun.', 'active', '2026-06-04 19:40:00'),
('Jalan MT Haryono', 'broken_vehicle', 'Sedan mogok mengeluarkan asap di dekat halte shelter busway.', 'resolved', '2026-06-04 20:10:00'),
('Gatot Subroto', 'road_obstacle', 'Pemasangan kabel fiber optik darurat memblokade lajur kiri.', 'active', '2026-06-04 21:00:00'),
('Cawang', 'accident', 'Senggolan truk muatan barang logistik luar kota.', 'resolved', '2026-06-04 21:45:00'),
('Jalan Raya Pasar Minggu', 'fallen_tree', 'Pohon pelindung tumbang akibat angin kencang malam hari.', 'resolved', '2026-06-04 22:30:00'),
('Jalan KH Abdullah Syafei', 'flood', 'Luapan air sungai meluber ke jalan dengan tinggi 40cm.', 'active', '2026-06-04 23:15:00'),
('Jalan Prof Dr Soepomo', 'broken_vehicle', 'Taksi konvensional mogok kehabisan daya aki.', 'resolved', '2026-06-04 23:50:00'),
('Jalan MT Haryono', 'accident', 'Kecelakaan tunggal mobil menabrak pembatas jalan beton.', 'resolved', '2026-06-05 00:30:00'),
('Gatot Subroto', 'road_obstacle', 'Pembersihan sisa puing kecelakaan oleh petugas kebersihan.', 'resolved', '2026-06-05 01:15:00'),
('Cawang', 'broken_vehicle', 'Truk tangki air mengalami patah as roda belakang.', 'active', '2026-06-05 02:00:00'),
('Manggarai', 'traffic_light_damage', 'Kerusakan panel sirkuit lampu merah persimpangan terminal.', 'resolved', '2026-06-05 03:00:00'),
('Jalan Raya Kalibata', 'road_obstacle', 'Penempatan kerucut jalan (cone) ilegal oleh juru parkir liar.', 'resolved', '2026-06-05 04:30:00'),
('Jalan KH Abdullah Syafei', 'accident', 'Kecelakaan sepeda motor sport dini hari akibat kecepatan tinggi.', 'resolved', '2026-06-05 05:00:00'),
('Jalan MT Haryono', 'broken_vehicle', 'Mobil travel mogok di bahu jalan tol dalam kota pararel.', 'resolved', '2026-06-05 06:15:00'),
('Gatot Subroto', 'accident', 'Kecelakaan minor antar taksi online saat berpindah lajur cepat.', 'resolved', '2026-06-05 06:45:00'),
('Cawang', 'road_obstacle', 'Besi penutup utilitas jalan (manhole) lepas menonjol ke atas.', 'active', '2026-06-05 07:05:00'),
('Jalan Raya Pasar Minggu', 'broken_vehicle', 'Angkot mogok mendadak di area drop-off penumpang.', 'resolved', '2026-06-05 07:30:00'),
('Jalan Prof Dr Soepomo', 'flood', 'Cileuncang/genangan air dangkal akibat tumpukan daun menyumbat tali air.', 'resolved', '2026-06-05 07:50:00'),
('Manggarai', 'accident', 'Insiden kendaraan roda dua menabrak bagian belakang separator pembatas.', 'resolved', '2026-06-05 08:10:00'),
('Jalan Raya Kalibata', 'fallen_tree', 'Ranting besar patah jatuh menimpa kabel instalasi listrik PLN.', 'active', '2026-06-05 08:40:00'),
('Jalan KH Abdullah Syafei', 'broken_vehicle', 'Kendaraan box ekspedisi mogok kehabisan solar di lajur lambat.', 'resolved', '2026-06-05 09:10:00');

INSERT IGNORE INTO zones (id, name, district, area_km2) VALUES
    (1, 'A', 'Jakarta Pusat',   48.13),
    (2, 'B', 'Jakarta Selatan', 141.27),
    (3, 'C', 'Jakarta Utara',   146.66),
    (4, 'D', 'Jakarta Barat',   129.54),
    (5, 'E', 'Jakarta Timur',   187.73);