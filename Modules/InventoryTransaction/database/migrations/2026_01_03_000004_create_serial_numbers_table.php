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
        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('goods_id')->constrained('goods')->onDelete('restrict');
            $table->foreignId('lot_id')->nullable()->constrained('stock_lots')->onDelete('restrict');
            $table->string('serial_no', 100);
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->onDelete('restrict');
            $table->string('status', 30);
            $table->timestampsTz();

            $table->unique(['organization_id', 'serial_no']);
        });

        DB::statement("ALTER TABLE serial_numbers ADD CONSTRAINT serial_numbers_status_check CHECK (status IN ('IN_STOCK', 'RESERVED', 'ISSUED', 'DAMAGED'));");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serial_numbers');
    }
};
