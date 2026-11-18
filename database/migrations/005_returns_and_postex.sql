-- Patches&Motifs - 005: order returns + PostEx courier support.

ALTER TABLE orders
    MODIFY status ENUM('pending_confirmation','confirmed','processing','dispatched','delivered','cancelled','on_hold','returned')
    NOT NULL DEFAULT 'pending_confirmation';
