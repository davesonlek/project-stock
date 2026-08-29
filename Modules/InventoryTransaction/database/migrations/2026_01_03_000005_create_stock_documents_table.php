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
        Schema::create('stock_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->string('document_no', 50);
            $table->string('document_type', 30);
            $table->string('status', 30)->default('DRAFT');

            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('source_location_id')->nullable()->constrained('warehouse_locations')->onDelete('restrict');

            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('destination_location_id')->nullable()->constrained('warehouse_locations')->onDelete('restrict');

            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('restrict');

            $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('approved_at')->nullable();

            $table->foreignUuid('posted_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('posted_at')->nullable();

            $table->uuid('reversal_of')->nullable();
            $table->text('remarks')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'document_no']);
            $table->index(['organization_id', 'status', 'document_type', 'created_at']);
        });

        Schema::table('stock_documents', function (Blueprint $table) {
            $table->foreign('reversal_of')->references('id')->on('stock_documents')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE stock_documents ADD CONSTRAINT stock_documents_document_type_check CHECK (document_type IN ('RECEIVE', 'ISSUE', 'TRANSFER', 'ADJUSTMENT'));");
        DB::statement("ALTER TABLE stock_documents ADD CONSTRAINT stock_documents_status_check CHECK (status IN ('DRAFT', 'PENDING', 'APPROVED', 'POSTED', 'REVERSED', 'CANCELLED'));");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_documents');
    }
};
