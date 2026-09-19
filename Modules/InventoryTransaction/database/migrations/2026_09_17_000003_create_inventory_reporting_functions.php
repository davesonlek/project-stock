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
            CREATE OR REPLACE FUNCTION fn_calculate_stock_daily_summary(
                p_organization_id uuid,
                p_date_from date,
                p_date_to date
            )
            RETURNS TABLE (
                organization_id uuid,
                summary_date date,
                warehouse_id bigint,
                location_id bigint,
                goods_id bigint,
                opening_qty numeric(18, 4),
                receive_qty numeric(18, 4),
                issue_qty numeric(18, 4),
                transfer_in_qty numeric(18, 4),
                transfer_out_qty numeric(18, 4),
                adjust_in_qty numeric(18, 4),
                adjust_out_qty numeric(18, 4),
                reversal_net_qty numeric(18, 4),
                net_movement_qty numeric(18, 4),
                closing_qty numeric(18, 4),
                movement_count bigint
            )
            LANGUAGE plpgsql
            STABLE
            SECURITY INVOKER
            AS \$\$
            BEGIN
                IF p_organization_id IS NULL THEN
                    RAISE EXCEPTION 'fn_calculate_stock_daily_summary: p_organization_id must not be NULL';
                END IF;

                IF p_date_from IS NULL THEN
                    RAISE EXCEPTION 'fn_calculate_stock_daily_summary: p_date_from must not be NULL';
                END IF;

                IF p_date_to IS NULL THEN
                    RAISE EXCEPTION 'fn_calculate_stock_daily_summary: p_date_to must not be NULL';
                END IF;

                IF p_date_to < p_date_from THEN
                    RAISE EXCEPTION 'fn_calculate_stock_daily_summary: p_date_to (%) must not be before p_date_from (%)',
                        p_date_to, p_date_from;
                END IF;

                RETURN QUERY
                SELECT
                    d.organization_id,
                    d.summary_date,
                    d.warehouse_id,
                    d.location_id,
                    d.goods_id,
                    COALESCE(opening.opening_qty, 0::numeric(18, 4)) AS opening_qty,
                    COALESCE(d.receive_qty, 0::numeric(18, 4)) AS receive_qty,
                    COALESCE(d.issue_qty, 0::numeric(18, 4)) AS issue_qty,
                    COALESCE(d.transfer_in_qty, 0::numeric(18, 4)) AS transfer_in_qty,
                    COALESCE(d.transfer_out_qty, 0::numeric(18, 4)) AS transfer_out_qty,
                    COALESCE(d.adjust_in_qty, 0::numeric(18, 4)) AS adjust_in_qty,
                    COALESCE(d.adjust_out_qty, 0::numeric(18, 4)) AS adjust_out_qty,
                    COALESCE(d.reversal_net_qty, 0::numeric(18, 4)) AS reversal_net_qty,
                    COALESCE(d.net_movement_qty, 0::numeric(18, 4)) AS net_movement_qty,
                    (
                        COALESCE(opening.opening_qty, 0::numeric(18, 4))
                        + COALESCE(d.net_movement_qty, 0::numeric(18, 4))
                    )::numeric(18, 4) AS closing_qty,
                    COALESCE(d.movement_count, 0::bigint) AS movement_count
                FROM vw_stock_movement_daily d
                LEFT JOIN LATERAL (
                    SELECT SUM(sm.quantity_delta)::numeric(18, 4) AS opening_qty
                    FROM stock_movements sm
                    WHERE sm.organization_id = p_organization_id
                      AND (sm.created_at AT TIME ZONE 'Asia/Bangkok')::date < d.summary_date
                      AND sm.warehouse_id = d.warehouse_id
                      AND sm.location_id = d.location_id
                      AND sm.goods_id = d.goods_id
                ) opening ON true
                WHERE d.organization_id = p_organization_id
                  AND d.summary_date >= p_date_from
                  AND d.summary_date <= p_date_to
                ORDER BY
                    d.summary_date,
                    d.warehouse_id,
                    d.location_id,
                    d.goods_id;
            END;
            \$\$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS fn_calculate_stock_daily_summary(uuid, date, date);');
    }
};
