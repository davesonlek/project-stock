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
        // 1. Create Sequence for Concurrency-safe Document Number Generation
        DB::statement("CREATE SEQUENCE IF NOT EXISTS stock_document_no_seq START 1;");

        // 2. Update stock_documents table with workflow fields and indexes
        Schema::table('stock_documents', function (Blueprint $table) {
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->index(['organization_id', 'document_no']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'document_type']);
        });

        // 3. Update stock_document_lines table for adjustment and lot preparation
        DB::statement("ALTER TABLE stock_document_lines DROP CONSTRAINT IF EXISTS stock_document_lines_quantity_check;");

        Schema::table('stock_document_lines', function (Blueprint $table) {
            $table->decimal('quantity', 18, 4)->nullable()->change();
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->string('lot_no', 100)->nullable();
            $table->date('manufactured_at')->nullable();
            $table->date('expired_at')->nullable();

            $table->index('document_id');
            $table->index('goods_id');
            $table->index('lot_id');
        });

        DB::statement("ALTER TABLE stock_document_lines ADD CONSTRAINT stock_document_lines_quantity_check CHECK (quantity IS NULL OR quantity > 0);");
        DB::statement("ALTER TABLE stock_document_lines ADD CONSTRAINT stock_document_lines_counted_quantity_check CHECK (counted_quantity IS NULL OR counted_quantity >= 0);");

        // 4. Recreate stock_document_line_serials to support new serials preparation before posting
        Schema::dropIfExists('stock_document_line_serials');

        Schema::create('stock_document_line_serials', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('document_id')->constrained('stock_documents')->onDelete('cascade');
            $table->foreignId('document_line_id')->constrained('stock_document_lines')->onDelete('cascade');
            $table->foreignId('serial_id')->nullable()->constrained('serial_numbers')->onDelete('restrict');
            $table->string('serial_no', 100);
            $table->timestampTz('created_at')->nullable();

            $table->unique(['document_line_id', 'serial_no']);
            $table->index(['document_line_id', 'serial_id']);
            $table->index(['document_id', 'serial_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_document_line_serials');

        Schema::create('stock_document_line_serials', function (Blueprint $table) {
            $table->foreignId('document_line_id')->constrained('stock_document_lines')->onDelete('cascade');
            $table->foreignId('serial_id')->constrained('serial_numbers')->onDelete('restrict');
            $table->primary(['document_line_id', 'serial_id']);
        });

        DB::statement("ALTER TABLE stock_document_lines DROP CONSTRAINT IF EXISTS stock_document_lines_counted_quantity_check;");
        DB::statement("ALTER TABLE stock_document_lines DROP CONSTRAINT IF EXISTS stock_document_lines_quantity_check;");

        Schema::table('stock_document_lines', function (Blueprint $table) {
            $table->dropColumn(['counted_quantity', 'lot_no', 'manufactured_at', 'expired_at']);
            $table->decimal('quantity', 18, 4)->nullable(false)->change();
        });

        DB::statement("ALTER TABLE stock_document_lines ADD CONSTRAINT stock_document_lines_quantity_check CHECK (quantity > 0);");

        Schema::table('stock_documents', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['submitted_by', 'submitted_at', 'cancelled_by', 'cancelled_at', 'cancel_reason']);
        });

        DB::statement("DROP SEQUENCE IF EXISTS stock_document_no_seq;");
    }
};
