<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('cascade'); // Nullable = Global price
            $table->string('unit_name'); // Unit name matching base_unit_name or product_unit_conversions.unit_name
            $table->enum('price_type', ['cost', 'sale']);
            $table->decimal('price', 15, 4); // Dynamic packaging/location pricing (micro-precision)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
