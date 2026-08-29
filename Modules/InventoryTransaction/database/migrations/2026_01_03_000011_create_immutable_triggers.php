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
        // 1. Immutable trigger for stock_movements
        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_stock_movement_mutation()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'Mutation rejected: stock_movements is an immutable, append-only ledger. UPDATE and DELETE operations are forbidden.';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_stock_movements_prevent_mutation
            BEFORE UPDATE OR DELETE ON stock_movements
            FOR EACH ROW
            EXECUTE FUNCTION prevent_stock_movement_mutation();
        ");

        // 2. Immutable trigger for audit_logs
        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_audit_log_mutation()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'Mutation rejected: audit_logs is an immutable, append-only audit trail. UPDATE and DELETE operations are forbidden.';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_audit_logs_prevent_mutation
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW
            EXECUTE FUNCTION prevent_audit_log_mutation();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_stock_movements_prevent_mutation ON stock_movements;
            DROP FUNCTION IF EXISTS prevent_stock_movement_mutation();
            DROP TRIGGER IF EXISTS trg_audit_logs_prevent_mutation ON audit_logs;
            DROP FUNCTION IF EXISTS prevent_audit_log_mutation();
        ");
    }
};
