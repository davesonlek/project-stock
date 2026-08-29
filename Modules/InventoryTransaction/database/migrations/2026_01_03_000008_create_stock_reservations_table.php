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
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('goods_id')->constrained('goods')->onDelete('restrict');
            $table->foreignId('lot_id')->nullable()->constrained('stock_lots')->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('location_id')->constrained('warehouse_locations')->onDelete('restrict');
            $table->foreignUuid('document_id')->nullable()->constrained('stock_documents')->onDelete('set null');
            $table->foreignId('document_line_id')->nullable()->constrained('stock_document_lines')->onDelete('set null');
            $table->decimal('quantity', 18, 4);
            $table->string('status', 20);
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');
            $table->timestampsTz();

            $table->index(['organization_id', 'status', 'expires_at']);
        });

        DB::statement('ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_quantity_check CHECK (quantity > 0);');
        DB::statement("ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_status_check CHECK (status IN ('ACTIVE', 'CONSUMED', 'RELEASED', 'CANCELLED'));");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
