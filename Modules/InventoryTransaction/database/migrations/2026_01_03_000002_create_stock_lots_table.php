<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('goods_id')->constrained('goods')->onDelete('restrict');
            $table->string('lot_no', 100);
            $table->date('manufactured_at')->nullable();
            $table->date('expired_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['organization_id', 'goods_id', 'lot_no']);

            $table->index('goods_id');
            $table->index('expired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
