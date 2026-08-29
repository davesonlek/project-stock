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
        Schema::create('goods_suppliers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('goods_id')->constrained('goods')->onDelete('cascade');
            $table->string('supplier_sku', 50)->nullable();
            $table->decimal('purchase_price', 18, 4)->default(0);
            $table->integer('lead_time_days')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->unique(['organization_id', 'supplier_id', 'goods_id']);
        });

        DB::statement('ALTER TABLE goods_suppliers ADD CONSTRAINT goods_suppliers_purchase_price_check CHECK (purchase_price >= 0);');
        DB::statement('ALTER TABLE goods_suppliers ADD CONSTRAINT goods_suppliers_lead_time_days_check CHECK (lead_time_days >= 0);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_suppliers');
    }
};
