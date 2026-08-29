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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignUuid('document_id')->constrained('stock_documents')->onDelete('restrict');
            $table->foreignId('document_line_id')->constrained('stock_document_lines')->onDelete('restrict');
            $table->foreignId('goods_id')->constrained('goods')->onDelete('restrict');
            $table->foreignId('lot_id')->nullable()->constrained('stock_lots')->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('location_id')->constrained('warehouse_locations')->onDelete('restrict');
            $table->string('movement_type', 30);
            $table->decimal('quantity_delta', 18, 4);
            $table->unsignedBigInteger('reversal_of')->nullable();
            $table->foreignUuid('performed_by')->constrained('users')->onDelete('restrict');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('organization_id');
            $table->index('document_id');
            $table->index('goods_id');
            $table->index('lot_id');
            $table->index('warehouse_id');
            $table->index('location_id');
            $table->index('created_at');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('reversal_of')->references('id')->on('stock_movements')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_delta_nonzero CHECK (quantity_delta <> 0);');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (movement_type IN ('RECEIVE', 'ISSUE', 'TRANSFER_OUT', 'TRANSFER_IN', 'ADJUST_IN', 'ADJUST_OUT', 'REVERSAL'));");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
