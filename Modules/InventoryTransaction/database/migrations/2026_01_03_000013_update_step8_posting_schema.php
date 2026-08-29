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
        // 1. Update serial_numbers status check constraint to include 'MISSING'
        DB::statement('ALTER TABLE serial_numbers DROP CONSTRAINT IF EXISTS serial_numbers_status_check;');
        DB::statement("ALTER TABLE serial_numbers ADD CONSTRAINT serial_numbers_status_check CHECK (status IN ('IN_STOCK', 'RESERVED', 'ISSUED', 'DAMAGED', 'MISSING'));");

        // 2. Add unique constraint to stock_movements to prevent duplicate movement per line per location per type
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unique(
                ['document_line_id', 'movement_type', 'warehouse_id', 'location_id'],
                'stock_movements_line_type_location_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique('stock_movements_line_type_location_unique');
        });

        DB::statement('ALTER TABLE serial_numbers DROP CONSTRAINT IF EXISTS serial_numbers_status_check;');
        DB::statement("ALTER TABLE serial_numbers ADD CONSTRAINT serial_numbers_status_check CHECK (status IN ('IN_STOCK', 'RESERVED', 'ISSUED', 'DAMAGED'));");
    }
};
