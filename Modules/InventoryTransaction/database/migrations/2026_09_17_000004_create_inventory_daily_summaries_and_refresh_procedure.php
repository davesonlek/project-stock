<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_daily_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->date('summary_date');
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('warehouse_locations')->restrictOnDelete();
            $table->foreignId('goods_id')->constrained('goods')->restrictOnDelete();
            $table->decimal('opening_qty', 18, 4)->default(0);
            $table->decimal('receive_qty', 18, 4)->default(0);
            $table->decimal('issue_qty', 18, 4)->default(0);
            $table->decimal('transfer_in_qty', 18, 4)->default(0);
            $table->decimal('transfer_out_qty', 18, 4)->default(0);
            $table->decimal('adjust_in_qty', 18, 4)->default(0);
            $table->decimal('adjust_out_qty', 18, 4)->default(0);
            $table->decimal('reversal_net_qty', 18, 4)->default(0);
            $table->decimal('net_movement_qty', 18, 4)->default(0);
            $table->decimal('closing_qty', 18, 4)->default(0);
            $table->unsignedBigInteger('movement_count')->default(0);
            $table->timestampTz('refreshed_at');
            $table->timestampsTz();

            $table->unique(
                ['organization_id', 'summary_date', 'warehouse_id', 'location_id', 'goods_id'],
                'inventory_daily_summaries_unique_key'
            );

            $table->index(['organization_id', 'summary_date'], 'inventory_daily_summaries_org_date_idx');
            $table->index(['organization_id', 'warehouse_id', 'summary_date'], 'inventory_daily_summaries_org_wh_date_idx');
            $table->index(['organization_id', 'goods_id', 'summary_date'], 'inventory_daily_summaries_org_goods_date_idx');
        });

        DB::statement('ALTER TABLE inventory_daily_summaries ADD CONSTRAINT inventory_daily_summaries_receive_qty_check CHECK (receive_qty >= 0);');
        DB::statement('ALTER TABLE inventory_daily_summaries ADD CONSTRAINT inventory_daily_summaries_issue_qty_check CHECK (issue_qty >= 0);');
        DB::statement('ALTER TABLE inventory_daily_summaries ADD CONSTRAINT inventory_daily_summaries_transfer_in_qty_check CHECK (transfer_in_qty >= 0);');
        DB::statement('ALTER TABLE inventory_daily_summaries ADD CONSTRAINT inventory_daily_summaries_transfer_out_qty_check CHECK (transfer_out_qty >= 0);');
        DB::statement('ALTER TABLE inventory_daily_summaries ADD CONSTRAINT inventory_daily_summaries_adjust_in_qty_check CHECK (adjust_in_qty >= 0);');
        DB::statement('ALTER TABLE inventory_daily_summaries ADD CONSTRAINT inventory_daily_summaries_adjust_out_qty_check CHECK (adjust_out_qty >= 0);');
        DB::statement('ALTER TABLE inventory_daily_summaries ADD CONSTRAINT inventory_daily_summaries_movement_count_check CHECK (movement_count >= 0);');
        DB::statement('ALTER TABLE inventory_daily_summaries ADD CONSTRAINT inventory_daily_summaries_closing_qty_check CHECK (closing_qty = opening_qty + net_movement_qty);');

        DB::unprepared("
            CREATE OR REPLACE PROCEDURE sp_refresh_inventory_daily_summary(
                p_organization_id uuid,
                p_date_from date,
                p_date_to date
            )
            LANGUAGE plpgsql
            SECURITY INVOKER
            AS \$\$
            BEGIN
                IF p_organization_id IS NULL THEN
                    RAISE EXCEPTION 'sp_refresh_inventory_daily_summary: p_organization_id must not be NULL';
                END IF;

                IF p_date_from IS NULL THEN
                    RAISE EXCEPTION 'sp_refresh_inventory_daily_summary: p_date_from must not be NULL';
                END IF;

                IF p_date_to IS NULL THEN
                    RAISE EXCEPTION 'sp_refresh_inventory_daily_summary: p_date_to must not be NULL';
                END IF;

                IF p_date_to < p_date_from THEN
                    RAISE EXCEPTION 'sp_refresh_inventory_daily_summary: p_date_to (%) must not be before p_date_from (%)',
                        p_date_to, p_date_from;
                END IF;

                PERFORM pg_advisory_xact_lock(
                    hashtext(p_organization_id::text),
                    hashtext(p_date_from::text || '|' || p_date_to::text)
                );

                INSERT INTO inventory_daily_summaries (
                    organization_id,
                    summary_date,
                    warehouse_id,
                    location_id,
                    goods_id,
                    opening_qty,
                    receive_qty,
                    issue_qty,
                    transfer_in_qty,
                    transfer_out_qty,
                    adjust_in_qty,
                    adjust_out_qty,
                    reversal_net_qty,
                    net_movement_qty,
                    closing_qty,
                    movement_count,
                    refreshed_at,
                    created_at,
                    updated_at
                )
                SELECT
                    calc.organization_id,
                    calc.summary_date,
                    calc.warehouse_id,
                    calc.location_id,
                    calc.goods_id,
                    calc.opening_qty,
                    calc.receive_qty,
                    calc.issue_qty,
                    calc.transfer_in_qty,
                    calc.transfer_out_qty,
                    calc.adjust_in_qty,
                    calc.adjust_out_qty,
                    calc.reversal_net_qty,
                    calc.net_movement_qty,
                    calc.closing_qty,
                    calc.movement_count,
                    NOW(),
                    NOW(),
                    NOW()
                FROM fn_calculate_stock_daily_summary(
                    p_organization_id,
                    p_date_from,
                    p_date_to
                ) AS calc
                ON CONFLICT ON CONSTRAINT inventory_daily_summaries_unique_key
                DO UPDATE SET
                    opening_qty = EXCLUDED.opening_qty,
                    receive_qty = EXCLUDED.receive_qty,
                    issue_qty = EXCLUDED.issue_qty,
                    transfer_in_qty = EXCLUDED.transfer_in_qty,
                    transfer_out_qty = EXCLUDED.transfer_out_qty,
                    adjust_in_qty = EXCLUDED.adjust_in_qty,
                    adjust_out_qty = EXCLUDED.adjust_out_qty,
                    reversal_net_qty = EXCLUDED.reversal_net_qty,
                    net_movement_qty = EXCLUDED.net_movement_qty,
                    closing_qty = EXCLUDED.closing_qty,
                    movement_count = EXCLUDED.movement_count,
                    refreshed_at = EXCLUDED.refreshed_at,
                    updated_at = EXCLUDED.updated_at;
            END;
            \$\$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_refresh_inventory_daily_summary(uuid, date, date);');
        Schema::dropIfExists('inventory_daily_summaries');
    }
};
