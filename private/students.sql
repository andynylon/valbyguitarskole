-- The Elever page creates these tables on its own.
-- Run this in phpMyAdmin on valbyguitarskole_dk_db only if that page says the tables are missing.

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquiry_id INT UNSIGNED NULL,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(200) NOT NULL,
    phone VARCHAR(50) NOT NULL DEFAULT '',
    instrument VARCHAR(80) NOT NULL DEFAULT '',
    status ENUM('trial', 'active', 'pause', 'finished') NOT NULL DEFAULT 'active',
    plan ENUM('none', 'trial', 'card5', 'card10', 'halfyear') NOT NULL DEFAULT 'none',
    lessons_total INT UNSIGNED NOT NULL DEFAULT 0,
    lessons_used INT UNSIGNED NOT NULL DEFAULT 0,
    weekday TINYINT UNSIGNED NULL,
    lesson_time TIME NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    note VARCHAR(2000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY students_enquiry (enquiry_id),
    KEY students_status (status),
    KEY students_period_end (period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    kind ENUM('signup', 'lesson', 'purchase', 'status', 'note', 'change') NOT NULL,
    title VARCHAR(200) NOT NULL,
    detail VARCHAR(2000) NOT NULL DEFAULT '',
    happened_on DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY student_events_student (student_id, happened_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
