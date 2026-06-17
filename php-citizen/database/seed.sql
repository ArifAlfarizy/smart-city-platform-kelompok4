INSERT INTO citizens
(user_id, nik, name, zone, phone)
VALUES
(1,'3141111111111111','Jane Doe','A','081111111111'),
(2,'3152222222222222','John Doe','B','082222222222'),
(3,'3163333333333333','Joe Public','C','083333333333');

INSERT INTO reports
(citizen_id, category, zone, description, status)
VALUES
(
1,
'jalan_rusak',
'A',
'Jalan ambles',
'pending'
),
(
2,
'lampu_mati',
'B',
'lampu mati seharian',
'process'
),
(
3,
'sampah',
'C',
'tumpukan sampah belum diangkat sejak seminggu lalu',
'completed'
);

INSERT INTO notifications
(citizen_id, title, message, is_read)
VALUES
(
1,
'Laporan Diterima',
'Laporan Anda berhasil diterima',
FALSE
),
(
2,
'Laporan Diproses',
'Laporan sedang diproses petugas',
FALSE
),
(
3,
'Laporan Selesai',
'Laporan telah ditindaklanjuti',
TRUE
);