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
        Schema::create('stock_document_line_serials', function (Blueprint $table) {
            $table->foreignId('document_line_id')->constrained('stock_document_lines')->onDelete('cascade');
            $table->foreignId('serial_id')->constrained('serial_numbers')->onDelete('restrict');

            $table->primary(['document_line_id', 'serial_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_document_line_serials');
    }
};
