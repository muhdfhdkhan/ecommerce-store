-- Patches&Motifs - 008: custom size for made-to-order cart lines.
--
-- Made-to-order patches/motifs can be ordered at any size inside the admin
-- range (Settings - Patch & motif sizing). The chosen size is stored on the
-- cart line and copied to order_items.variant_label when the order is placed.
-- Empty string = the line uses the variant's preset size label, so the unique
-- key stays valid for existing carts.

ALTER TABLE cart_items
    ADD COLUMN IF NOT EXISTS custom_size VARCHAR(32) NOT NULL DEFAULT '' AFTER variant_id;

-- The foreign key on cart_id used the leftmost column of uq_cart_line; give it
-- its own index before the unique key is rebuilt.
ALTER TABLE cart_items
    ADD KEY IF NOT EXISTS idx_ci_cart (cart_id);

ALTER TABLE cart_items
    DROP INDEX IF EXISTS uq_cart_line;

ALTER TABLE cart_items
    ADD UNIQUE KEY IF NOT EXISTS uq_cart_line (cart_id, variant_id, custom_size);
