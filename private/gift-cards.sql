-- Run this once in phpMyAdmin on the existing enquiry database.
-- Select valbyguitarskole_dk_db first. If a column already exists, skip that statement.

ALTER TABLE enquiries
    ADD COLUMN recipient VARCHAR(200) NOT NULL DEFAULT '' AFTER language,
    ADD COLUMN greeting VARCHAR(500) NOT NULL DEFAULT '' AFTER recipient;

CREATE TABLE IF NOT EXISTS gift_cards (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquiry_id INT UNSIGNED NOT NULL,
    code VARCHAR(16) NOT NULL,
    amount VARCHAR(200) NOT NULL,
    buyer_name VARCHAR(200) NOT NULL,
    buyer_email VARCHAR(200) NOT NULL,
    recipient VARCHAR(200) NOT NULL,
    greeting VARCHAR(500) NOT NULL DEFAULT '',
    language CHAR(2) NOT NULL DEFAULT 'da',
    status ENUM('issued', 'redeemed') NOT NULL DEFAULT 'issued',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    redeemed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY gift_cards_code (code),
    UNIQUE KEY gift_cards_enquiry (enquiry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
