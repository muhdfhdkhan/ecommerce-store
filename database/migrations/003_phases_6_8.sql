-- Patches&Motifs — Phases 6-8 schema (suppliers/purchasing, hero banners, email log, SEO/perf indexes)

-- Hero slides reuse the banners table: image + subtitle columns.
ALTER TABLE banners
    ADD COLUMN image_path VARCHAR(255) NULL AFTER title,
    ADD COLUMN subtitle VARCHAR(255) NULL AFTER image_path;

-- Patch/motif variants carry a length dimension (e.g. "12 inch border").
ALTER TABLE product_variants
    ADD COLUMN length_label VARCHAR(32) NULL AFTER color_label;

-- ============ PHASE 6: Suppliers & purchasing ============
CREATE TABLE IF NOT EXISTS suppliers (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(120)    NOT NULL,
    contact_name VARCHAR(120)    NULL,
    phone        VARCHAR(24)     NULL,
    email        VARCHAR(254)    NULL,
    address      VARCHAR(500)    NULL,
    notes        VARCHAR(500)    NULL,
    is_active    TINYINT(1)      NOT NULL DEFAULT 1,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    po_number     VARCHAR(32)     NOT NULL,             -- PO-YYYY-NNNNNN
    supplier_id   BIGINT UNSIGNED NOT NULL,
    status        ENUM('draft','ordered','received','cancelled') NOT NULL DEFAULT 'draft',
    notes         VARCHAR(500)    NULL,
    total_cost    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    ordered_at    DATETIME        NULL,
    received_at   DATETIME        NULL,
    created_by    BIGINT UNSIGNED NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_po_number (po_number),
    KEY idx_po_supplier (supplier_id, status),
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    po_id       BIGINT UNSIGNED NOT NULL,
    variant_id  BIGINT UNSIGNED NOT NULL,
    quantity    INT             NOT NULL,
    unit_cost   DECIMAL(12,2)   NOT NULL,
    line_cost   DECIMAL(12,2)   NOT NULL,
    PRIMARY KEY (id),
    KEY idx_poi_po (po_id),
    CONSTRAINT fk_poi_po FOREIGN KEY (po_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_poi_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ PHASE 7: Email notifications log ============
CREATE TABLE IF NOT EXISTS email_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    to_email   VARCHAR(254)    NOT NULL,
    subject    VARCHAR(200)    NOT NULL,
    body       TEXT            NULL,
    event      VARCHAR(64)     NOT NULL,      -- order_placed|order_status|payment_verified|...
    status     ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    error      VARCHAR(255)    NULL,
    order_id   BIGINT UNSIGNED NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at    DATETIME        NULL,
    PRIMARY KEY (id),
    KEY idx_email_event (event, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ PHASE 8: Performance indexes ============
ALTER TABLE order_items
    ADD INDEX idx_items_variant (variant_id);
ALTER TABLE cart_items
    ADD INDEX idx_ci_variant (variant_id);
ALTER TABLE order_status_history
    ADD INDEX idx_history_status (to_status);
