ALTER TABLE enquiries
    ADD COLUMN newsletter TINYINT(1) NOT NULL DEFAULT 0 AFTER language;
