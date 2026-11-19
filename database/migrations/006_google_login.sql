-- Patches&Motifs - 006: Google Sign-In for customers.
-- google_id stores the stable Google account id; plain email login still works
-- for accounts that also set a password. Unique so one Google account links once.

ALTER TABLE customers
    ADD COLUMN google_id VARCHAR(64) NULL AFTER password_hash,
    ADD UNIQUE KEY uq_customers_google (google_id);

INSERT IGNORE INTO store_settings (setting_key, setting_value) VALUES
    ('google_client_id', ''),
    ('google_client_secret', '');
