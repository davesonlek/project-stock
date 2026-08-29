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
        // 1. Update stock_reservations schema
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreignUuid('released_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('released_at')->nullable();
            $table->foreignUuid('consumed_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('consumed_at')->nullable();
        });

        // 2. Update stock_reservations status check constraint
        DB::statement('ALTER TABLE stock_reservations DROP CONSTRAINT IF EXISTS stock_reservations_status_check;');
        DB::statement("ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_status_check CHECK (status IN ('ACTIVE', 'CONSUMED', 'RELEASED', 'CANCELLED', 'EXPIRED'));");

        // 3. Partial unique index for active reservation per line
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_stock_reservations_active_line ON stock_reservations (document_line_id) WHERE status = \'ACTIVE\';');

        // 4. Update serial_numbers status check constraint
        DB::statement('ALTER TABLE serial_numbers DROP CONSTRAINT IF EXISTS serial_numbers_status_check;');
        DB::statement("ALTER TABLE serial_numbers ADD CONSTRAINT serial_numbers_status_check CHECK (status IN ('IN_STOCK', 'RESERVED', 'ISSUED', 'DAMAGED', 'MISSING', 'REVERSED'));");

        // 5. Update stock_documents document_type check constraint
        DB::statement('ALTER TABLE stock_documents DROP CONSTRAINT IF EXISTS stock_documents_document_type_check;');
        DB::statement("ALTER TABLE stock_documents ADD CONSTRAINT stock_documents_document_type_check CHECK (document_type IN ('RECEIVE', 'ISSUE', 'TRANSFER', 'ADJUSTMENT', 'REVERSAL'));");

        // 6. Partial unique index for one reversal document per original document
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_stock_documents_reversal_of ON stock_documents (reversal_of) WHERE reversal_of IS NOT NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_stock_documents_reversal_of;');
        DB::statement('DROP INDEX IF EXISTS idx_stock_reservations_active_line;');

        DB::statement('ALTER TABLE stock_documents DROP CONSTRAINT IF EXISTS stock_documents_document_type_check;');
        DB::statement("ALTER TABLE stock_documents ADD CONSTRAINT stock_documents_document_type_check CHECK (document_type IN ('RECEIVE', 'ISSUE', 'TRANSFER', 'ADJUSTMENT'));");

        DB::statement('ALTER TABLE serial_numbers DROP CONSTRAINT IF EXISTS serial_numbers_status_check;');
        DB::statement("ALTER TABLE serial_numbers ADD CONSTRAINT serial_numbers_status_check CHECK (status IN ('IN_STOCK', 'RESERVED', 'ISSUED', 'DAMAGED', 'MISSING'));");

        DB::statement('ALTER TABLE stock_reservations DROP CONSTRAINT IF EXISTS stock_reservations_status_check;');
        DB::statement("ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_status_check CHECK (status IN ('ACTIVE', 'CONSUMED', 'RELEASED', 'CANCELLED'));");

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropForeign(['released_by']);
            $table->dropForeign(['consumed_by']);
            $table->dropColumn(['released_by', 'released_at', 'consumed_by', 'consumed_at']);
        });
    }
};
