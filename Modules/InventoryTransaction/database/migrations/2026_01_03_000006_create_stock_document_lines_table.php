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
        Schema::create('stock_document_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('document_id')->constrained('stock_documents')->onDelete('cascade');
            $table->foreignId('goods_id')->constrained('goods')->onDelete('restrict');
            $table->foreignId('lot_id')->nullable()->constrained('stock_lots')->onDelete('restrict');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->text('remarks')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE stock_document_lines ADD CONSTRAINT stock_document_lines_quantity_check CHECK (quantity > 0);');
        DB::statement('ALTER TABLE stock_document_lines ADD CONSTRAINT stock_document_lines_unit_cost_check CHECK (unit_cost IS NULL OR unit_cost >= 0);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_document_lines');
    }
};
