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
        Schema::create('stock_lot_balances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('location_id')->constrained('warehouse_locations')->onDelete('restrict');
            $table->foreignId('lot_id')->constrained('stock_lots')->onDelete('restrict');
            $table->decimal('on_hand', 18, 4)->default(0);
            $table->decimal('reserved', 18, 4)->default(0);
            $table->timestampsTz();

            $table->unique(['organization_id', 'warehouse_id', 'location_id', 'lot_id']);

            $table->index('lot_id');
            $table->index('location_id');
        });

        DB::statement('ALTER TABLE stock_lot_balances ADD CONSTRAINT stock_lot_balances_on_hand_check CHECK (on_hand >= 0);');
        DB::statement('ALTER TABLE stock_lot_balances ADD CONSTRAINT stock_lot_balances_reserved_check CHECK (reserved >= 0);');
        DB::statement('ALTER TABLE stock_lot_balances ADD CONSTRAINT stock_lot_balances_reserved_le_on_hand_check CHECK (reserved <= on_hand);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_lot_balances');
    }
};
