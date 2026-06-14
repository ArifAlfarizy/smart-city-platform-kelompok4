CREATE TABLE citizens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nik VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    zone ENUM('A','B','C') NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    category ENUM(
        'jalan_rusak',
        'lampu_mati',
        'sampah',
        'banjir',
        'lainnya'
    ) NOT NULL,
    zone ENUM('A','B','C') NOT NULL,
    description TEXT NOT NULL,
    status ENUM(
        'pending',
        'process',
        'completed'
    ) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);