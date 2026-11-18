-- Patches&Motifs — Phases 2-5 schema (catalog, cart, orders, payments, shipping)
-- InnoDB, utf8mb4, DECIMAL(12,2) money, UTC timestamps, snapshot rules (PROJECT_BRIEF §24).

-- ============ PHASE 2: Catalog ============
CREATE TABLE IF NOT EXISTS categories (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id       BIGINT UNSIGNED NULL,
    name            VARCHAR(120)    NOT NULL,
    slug            VARCHAR(160)    NOT NULL,
    description     VARCHAR(500)    NULL,
    image_path      VARCHAR(255)    NULL,
    display_order   INT             NOT NULL DEFAULT 0,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    meta_title      VARCHAR(160)    NULL,
    meta_description VARCHAR(300)   NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at      DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id, status),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(180)    NOT NULL,
    slug             VARCHAR(180)    NOT NULL,
    sku              VARCHAR(64)     NOT NULL,
    category_id      BIGINT UNSIGNED NULL,
    short_description VARCHAR(400)   NULL,
    description      MEDIUMTEXT      NULL,
    base_price       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    sale_price       DECIMAL(12,2)   NULL,
    status           ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    is_featured      TINYINT(1)      NOT NULL DEFAULT 0,
    made_to_order    TINYINT(1)      NOT NULL DEFAULT 0,
    lead_time_days   INT             NULL,
    weight_grams     INT             NULL,
    meta_title       VARCHAR(160)    NULL,
    meta_description VARCHAR(300)    NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME        NULL,
    FULLTEXT KEY ft_products_search (name, short_description),
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_status (status, is_featured),
    KEY idx_products_category (category_id, status),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  BIGINT UNSIGNED NOT NULL,
    file_path   VARCHAR(255)    NOT NULL,
    alt_text    VARCHAR(255)    NULL,
    sort_order  INT             NOT NULL DEFAULT 0,
    is_primary  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_images_product (product_id, sort_order),
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id   BIGINT UNSIGNED NOT NULL,
    sku          VARCHAR(64)     NOT NULL,
    size_label   VARCHAR(32)     NULL,
    color_label  VARCHAR(32)     NULL,
    price        DECIMAL(12,2)   NOT NULL,
    sale_price   DECIMAL(12,2)   NULL,
    weight_grams INT             NULL,
    is_active    TINYINT(1)      NOT NULL DEFAULT 1,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at   DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_variants_sku (sku),
    KEY idx_variants_product (product_id, is_active),
    CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory (
    variant_id          BIGINT UNSIGNED NOT NULL,
    quantity_on_hand    INT             NOT NULL DEFAULT 0,
    quantity_reserved   INT             NOT NULL DEFAULT 0,
    low_stock_threshold INT             NOT NULL DEFAULT 5,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (variant_id),
    CONSTRAINT fk_inventory_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    variant_id BIGINT UNSIGNED NOT NULL,
    change_qty INT             NOT NULL,
    reason     VARCHAR(64)     NOT NULL,       -- purchase|sale|adjustment|damage|reservation|release|restock
    ref_type   VARCHAR(32)     NULL,
    ref_id     BIGINT UNSIGNED NULL,
    user_id    BIGINT UNSIGNED NULL,
    note       VARCHAR(255)    NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_movements_variant (variant_id, created_at),
    CONSTRAINT fk_movements_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collections (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(120)    NOT NULL,
    slug        VARCHAR(160)    NOT NULL,
    description VARCHAR(400)    NULL,
    image_path  VARCHAR(255)    NULL,
    sort_order  INT             NOT NULL DEFAULT 0,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    starts_at   DATETIME        NULL,
    ends_at     DATETIME        NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_collections_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collection_products (
    collection_id BIGINT UNSIGNED NOT NULL,
    product_id    BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (collection_id, product_id),
    CONSTRAINT fk_cp_collection FOREIGN KEY (collection_id) REFERENCES collections (id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ PHASE 4: Customers ============
CREATE TABLE IF NOT EXISTS customers (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(120)    NOT NULL,
    email          VARCHAR(254)    NOT NULL,
    phone          VARCHAR(24)     NULL,
    password_hash  VARCHAR(255)    NOT NULL,
    status         ENUM('active','blocked') NOT NULL DEFAULT 'active',
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email),
    KEY idx_customers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_addresses (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id   BIGINT UNSIGNED NOT NULL,
    full_name     VARCHAR(120)    NOT NULL,
    phone         VARCHAR(24)     NOT NULL,
    address_line1 VARCHAR(255)    NOT NULL,
    address_line2 VARCHAR(255)    NULL,
    city          VARCHAR(80)     NOT NULL,
    province      VARCHAR(80)     NULL,
    postal_code   VARCHAR(16)     NULL,
    country       CHAR(2)         NOT NULL DEFAULT 'PK',
    is_default    TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at    DATETIME        NULL,
    PRIMARY KEY (id),
    KEY idx_addresses_customer (customer_id),
    CONSTRAINT fk_addresses_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope      VARCHAR(16)     NOT NULL,           -- customer | admin
    email      VARCHAR(254)    NOT NULL,
    token_hash CHAR(64)        NOT NULL,           -- sha256 of token, never the raw token
    expires_at DATETIME        NOT NULL,
    used_at    DATETIME        NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reset_token (token_hash),
    KEY idx_reset_lookup (scope, email, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ PHASE 4: Cart ============
CREATE TABLE IF NOT EXISTS carts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id  BIGINT UNSIGNED NULL,
    session_id   VARCHAR(64)     NOT NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carts_session (session_id),
    KEY idx_carts_customer (customer_id),
    CONSTRAINT fk_carts_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cart_id    BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NOT NULL,
    quantity   INT             NOT NULL,
    added_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_line (cart_id, variant_id),
    CONSTRAINT fk_ci_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ PHASE 4/5: Promotions, Shipping ============
CREATE TABLE IF NOT EXISTS coupons (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code               VARCHAR(32)     NOT NULL,
    type               ENUM('percent','fixed') NOT NULL,
    value              DECIMAL(12,2)   NOT NULL,
    min_subtotal       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    usage_limit        INT             NULL,
    used_count         INT             NOT NULL DEFAULT 0,
    per_customer_limit INT             NULL,
    starts_at          DATETIME        NULL,
    ends_at            DATETIME        NULL,
    is_active          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coupons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_zones (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(80)     NOT NULL,
    cities     TEXT            NULL,   -- comma-separated city names matched case-insensitively
    is_default TINYINT(1)      NOT NULL DEFAULT 0,
    is_active  TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_methods (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    zone_id           BIGINT UNSIGNED NOT NULL,
    name              VARCHAR(80)     NOT NULL,
    flat_fee          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    free_over_amount  DECIMAL(12,2)   NULL,
    estimate_days     VARCHAR(32)     NULL,
    is_active         TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_methods_zone (zone_id, is_active),
    CONSTRAINT fk_methods_zone FOREIGN KEY (zone_id) REFERENCES shipping_zones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ PHASE 5: Orders & Payments ============
CREATE TABLE IF NOT EXISTS orders (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number     VARCHAR(32)     NOT NULL,          -- EMB-YYYY-NNNNNN
    customer_id      BIGINT UNSIGNED NULL,
    full_name        VARCHAR(120)    NOT NULL,
    email            VARCHAR(254)    NOT NULL,
    phone            VARCHAR(24)     NOT NULL,
    status           ENUM('pending_confirmation','confirmed','processing','dispatched','delivered','cancelled','on_hold') NOT NULL DEFAULT 'pending_confirmation',
    payment_status   ENUM('pending','awaiting_verification','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    payment_method   ENUM('cod','bank_transfer') NOT NULL,
    subtotal         DECIMAL(12,2)   NOT NULL,
    discount_total   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    coupon_code      VARCHAR(32)     NULL,
    shipping_total   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    grand_total      DECIMAL(12,2)   NOT NULL,
    currency         CHAR(3)         NOT NULL DEFAULT 'PKR',
    shipping_address JSON            NOT NULL,
    customer_note    VARCHAR(500)    NULL,
    admin_note       TEXT            NULL,
    idempotency_key  VARCHAR(64)     NOT NULL,
    tracking_number  VARCHAR(64)     NULL,
    courier_name     VARCHAR(64)     NULL,
    placed_at        DATETIME        NOT NULL,
    cancelled_at     DATETIME        NULL,
    cancel_reason    VARCHAR(255)    NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_number (order_number),
    UNIQUE KEY uq_orders_idem (idempotency_key),
    KEY idx_orders_status (status, placed_at),
    KEY idx_orders_customer (customer_id),
    KEY idx_orders_email (email),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id      BIGINT UNSIGNED NOT NULL,
    variant_id    BIGINT UNSIGNED NULL,
    product_id    BIGINT UNSIGNED NULL,
    product_name  VARCHAR(180)    NOT NULL,   -- immutable snapshot
    variant_label VARCHAR(80)     NULL,
    sku           VARCHAR(64)     NOT NULL,
    unit_price    DECIMAL(12,2)   NOT NULL,
    quantity      INT             NOT NULL,
    line_total    DECIMAL(12,2)   NOT NULL,
    PRIMARY KEY (id),
    KEY idx_items_order (order_id),
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_history (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id   BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(32)    NULL,
    to_status  VARCHAR(32)     NOT NULL,
    note       VARCHAR(255)    NULL,
    user_id    BIGINT UNSIGNED NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_history_order (order_id, created_at),
    CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     BIGINT UNSIGNED NOT NULL,
    method       ENUM('cod','bank_transfer') NOT NULL,
    status       ENUM('pending','awaiting_verification','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    amount       DECIMAL(12,2)   NOT NULL,
    reference    VARCHAR(64)     NULL,             -- bank transfer ref entered by staff
    receipt_path VARCHAR(255)    NULL,             -- private storage path (SECURITY.md §12)
    verified_by  BIGINT UNSIGNED NULL,
    verified_at  DATETIME        NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payments_order (order_id),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipments (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        BIGINT UNSIGNED NOT NULL,
    courier_name    VARCHAR(64)     NOT NULL,
    tracking_number VARCHAR(64)     NOT NULL,
    tracking_url    VARCHAR(255)    NULL,
    dispatched_at   DATETIME        NOT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_shipments_order (order_id),
    CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ CMS (homepage control) ============
CREATE TABLE IF NOT EXISTS banners (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title        VARCHAR(120)    NOT NULL,
    link_url     VARCHAR(255)    NULL,
    sort_order   INT             NOT NULL DEFAULT 0,
    is_active    TINYINT(1)      NOT NULL DEFAULT 1,
    starts_at    DATETIME        NULL,
    ends_at      DATETIME        NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(160)    NOT NULL,
    slug       VARCHAR(180)    NOT NULL,
    content    MEDIUMTEXT      NULL,
    status     ENUM('draft','published') NOT NULL DEFAULT 'draft',
    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
