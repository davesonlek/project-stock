<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE VIEW vw_stock_availability
            WITH (security_invoker = true)
            AS
            SELECT
                sb.organization_id,
                sb.warehouse_id,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                sb.location_id,
                wl.code AS location_code,
                wl.name AS location_name,
                sb.goods_id,
                p.sku,
                p.name AS goods_name,
                g.product_id,
                p.name AS product_name,
                g.unit_id,
                u.code AS unit_code,
                sb.on_hand::numeric(18, 4) AS on_hand,
                sb.reserved::numeric(18, 4) AS reserved,
                (sb.on_hand - sb.reserved)::numeric(18, 4) AS available,
                (sb.on_hand = 0) AS is_zero_stock,
                (sb.reserved > 0) AS has_reservation,
                sb.updated_at AS balance_updated_at
            FROM stock_balances sb
            INNER JOIN goods g ON g.id = sb.goods_id
            INNER JOIN products p ON p.id = g.product_id
            INNER JOIN units u ON u.id = g.unit_id
            INNER JOIN warehouses w ON w.id = sb.warehouse_id
            INNER JOIN warehouse_locations wl ON wl.id = sb.location_id;
        ");

        DB::unprepared("
            CREATE OR REPLACE VIEW vw_stock_movement_daily
            WITH (security_invoker = true)
            AS
            SELECT
                sm.organization_id,
                (sm.created_at AT TIME ZONE 'Asia/Bangkok')::date AS summary_date,
                sm.warehouse_id,
                sm.location_id,
                sm.goods_id,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'RECEIVE' THEN sm.quantity_delta ELSE 0 END), 0)::numeric(18, 4) AS receive_qty,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'ISSUE' THEN -sm.quantity_delta ELSE 0 END), 0)::numeric(18, 4) AS issue_qty,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'TRANSFER_IN' THEN sm.quantity_delta ELSE 0 END), 0)::numeric(18, 4) AS transfer_in_qty,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'TRANSFER_OUT' THEN -sm.quantity_delta ELSE 0 END), 0)::numeric(18, 4) AS transfer_out_qty,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'ADJUST_IN' THEN sm.quantity_delta ELSE 0 END), 0)::numeric(18, 4) AS adjust_in_qty,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'ADJUST_OUT' THEN -sm.quantity_delta ELSE 0 END), 0)::numeric(18, 4) AS adjust_out_qty,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'REVERSAL' THEN sm.quantity_delta ELSE 0 END), 0)::numeric(18, 4) AS reversal_net_qty,
                COALESCE(SUM(sm.quantity_delta), 0)::numeric(18, 4) AS net_movement_qty,
                COUNT(*)::bigint AS movement_count,
                MIN(sm.created_at) AS first_movement_at,
                MAX(sm.created_at) AS last_movement_at
            FROM stock_movements sm
            GROUP BY
                sm.organization_id,
                (sm.created_at AT TIME ZONE 'Asia/Bangkok')::date,
                sm.warehouse_id,
                sm.location_id,
                sm.goods_id;
        ");

        DB::unprepared("
            CREATE OR REPLACE VIEW vw_inventory_reconciliation
            WITH (security_invoker = true)
            AS
            WITH movement_totals AS (
                SELECT
                    organization_id,
                    warehouse_id,
                    location_id,
                    goods_id,
                    SUM(quantity_delta)::numeric(18, 4) AS ledger_on_hand
                FROM stock_movements
                GROUP BY organization_id, warehouse_id, location_id, goods_id
            ),
            reconciled AS (
                SELECT
                    COALESCE(sb.organization_id, mt.organization_id) AS organization_id,
                    COALESCE(sb.warehouse_id, mt.warehouse_id) AS warehouse_id,
                    COALESCE(sb.location_id, mt.location_id) AS location_id,
                    COALESCE(sb.goods_id, mt.goods_id) AS goods_id,
                    COALESCE(sb.on_hand, 0)::numeric(18, 4) AS balance_on_hand,
                    COALESCE(mt.ledger_on_hand, 0)::numeric(18, 4) AS ledger_on_hand,
                    (
                        COALESCE(sb.on_hand, 0)::numeric(18, 4)
                        - COALESCE(mt.ledger_on_hand, 0)::numeric(18, 4)
                    ) AS difference_qty
                FROM stock_balances sb
                FULL OUTER JOIN movement_totals mt
                    ON sb.organization_id = mt.organization_id
                    AND sb.warehouse_id = mt.warehouse_id
                    AND sb.location_id = mt.location_id
                    AND sb.goods_id = mt.goods_id
            )
            SELECT
                r.organization_id,
                r.warehouse_id,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                r.location_id,
                wl.code AS location_code,
                wl.name AS location_name,
                r.goods_id,
                p.sku,
                p.name AS goods_name,
                r.balance_on_hand,
                r.ledger_on_hand,
                r.difference_qty,
                CASE
                    WHEN r.difference_qty = 0::numeric(18, 4) THEN 'MATCH'
                    ELSE 'MISMATCH'
                END AS reconciliation_status
            FROM reconciled r
            INNER JOIN goods g ON g.id = r.goods_id
            INNER JOIN products p ON p.id = g.product_id
            INNER JOIN warehouses w ON w.id = r.warehouse_id
            INNER JOIN warehouse_locations wl ON wl.id = r.location_id;
        ");

        DB::unprepared("
            CREATE OR REPLACE VIEW vw_lot_expiry
            WITH (security_invoker = true)
            AS
            SELECT
                slb.organization_id,
                sl.id AS lot_id,
                sl.lot_no,
                sl.goods_id,
                p.sku,
                p.name AS goods_name,
                slb.warehouse_id,
                w.code AS warehouse_code,
                slb.location_id,
                wl.code AS location_code,
                sl.manufactured_at,
                sl.expired_at,
                slb.on_hand::numeric(18, 4) AS on_hand,
                slb.reserved::numeric(18, 4) AS reserved,
                (slb.on_hand - slb.reserved)::numeric(18, 4) AS available,
                CASE
                    WHEN sl.expired_at IS NULL THEN NULL::integer
                    ELSE (sl.expired_at - (NOW() AT TIME ZONE 'Asia/Bangkok')::date)::integer
                END AS days_to_expiry,
                CASE
                    WHEN sl.expired_at IS NULL THEN 'NO_EXPIRY'
                    WHEN sl.expired_at < (NOW() AT TIME ZONE 'Asia/Bangkok')::date THEN 'EXPIRED'
                    WHEN sl.expired_at <= (NOW() AT TIME ZONE 'Asia/Bangkok')::date + 7 THEN 'EXPIRING_7_DAYS'
                    WHEN sl.expired_at <= (NOW() AT TIME ZONE 'Asia/Bangkok')::date + 30 THEN 'EXPIRING_30_DAYS'
                    ELSE 'VALID'
                END AS expiry_status
            FROM stock_lot_balances slb
            INNER JOIN stock_lots sl ON sl.id = slb.lot_id
            INNER JOIN goods g ON g.id = sl.goods_id
            INNER JOIN products p ON p.id = g.product_id
            INNER JOIN warehouses w ON w.id = slb.warehouse_id
            INNER JOIN warehouse_locations wl ON wl.id = slb.location_id
            WHERE slb.on_hand > 0;
        ");

        DB::unprepared("
            CREATE OR REPLACE VIEW vw_active_reservations
            WITH (security_invoker = true)
            AS
            SELECT
                sr.organization_id,
                sr.id AS reservation_id,
                sr.document_id,
                sd.document_no,
                sr.document_line_id,
                sr.goods_id,
                p.sku,
                p.name AS goods_name,
                sr.lot_id,
                sl.lot_no,
                sr.warehouse_id,
                w.code AS warehouse_code,
                sr.location_id,
                wl.code AS location_code,
                sr.quantity::numeric(18, 4) AS quantity,
                sr.expires_at,
                sr.created_at,
                FLOOR(EXTRACT(EPOCH FROM (NOW() - sr.created_at)) / 60)::bigint AS reservation_age_minutes,
                CASE
                    WHEN sr.expires_at IS NULL THEN 'NO_EXPIRY'
                    WHEN sr.expires_at < NOW() THEN 'EXPIRED'
                    WHEN sr.expires_at <= NOW() + INTERVAL '60 minutes' THEN 'EXPIRING_SOON'
                    ELSE 'ACTIVE'
                END AS reservation_expiry_status
            FROM stock_reservations sr
            INNER JOIN goods g ON g.id = sr.goods_id
            INNER JOIN products p ON p.id = g.product_id
            INNER JOIN warehouses w ON w.id = sr.warehouse_id
            INNER JOIN warehouse_locations wl ON wl.id = sr.location_id
            LEFT JOIN stock_lots sl ON sl.id = sr.lot_id
            LEFT JOIN stock_documents sd ON sd.id = sr.document_id
            LEFT JOIN stock_document_lines sdl ON sdl.id = sr.document_line_id
            WHERE sr.status = 'ACTIVE';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS vw_active_reservations;');
        DB::unprepared('DROP VIEW IF EXISTS vw_lot_expiry;');
        DB::unprepared('DROP VIEW IF EXISTS vw_inventory_reconciliation;');
        DB::unprepared('DROP VIEW IF EXISTS vw_stock_movement_daily;');
        DB::unprepared('DROP VIEW IF EXISTS vw_stock_availability;');
    }
};
