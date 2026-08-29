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
        Schema::create('goods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('unit_id')->constrained('units')->onDelete('restrict');
            $table->string('barcode', 100)->nullable();
            $table->decimal('pack_size', 18, 4)->default(1);
            $table->decimal('cost', 18, 4)->default(0);
            $table->decimal('sell_price', 18, 4)->default(0);
            $table->boolean('is_lot_tracked')->default(false);
            $table->boolean('is_serial_tracked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE goods ADD CONSTRAINT goods_pack_size_check CHECK (pack_size > 0);');
        DB::statement('ALTER TABLE goods ADD CONSTRAINT goods_cost_check CHECK (cost >= 0);');
        DB::statement('ALTER TABLE goods ADD CONSTRAINT goods_sell_price_check CHECK (sell_price >= 0);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods');
    }
};
