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