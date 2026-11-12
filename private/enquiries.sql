CREATE TABLE IF NOT EXISTS enquiries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    offer VARCHAR(200) NOT NULL,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(200) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    student VARCHAR(200) NOT NULL,
    language CHAR(2) NOT NULL DEFAULT 'da',
    recipient VARCHAR(200) NOT NULL DEFAULT '',
    greeting VARCHAR(500) NOT NULL DEFAULT '',
    status ENUM('new', 'contacted', 'booked', 'closed') NOT NULL DEFAULT 'new',
    PRIMARY KEY (id),
    KEY enquiries_created_at (created_at),
    KEY enquiries_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
