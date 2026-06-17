USE smartcity;

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