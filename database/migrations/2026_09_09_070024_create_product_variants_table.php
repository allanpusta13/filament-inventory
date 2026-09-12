<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->unique();               // Unique SKU (e.g. 'PROD-COF-500G')
            $table->string('barcode')->nullable()->unique(); // Scanner GTIN
            $table->string('name');                          // Variant identifier (e.g. "500g Whole Bean")
            $table->string('base_unit_name');                // Lowest non-divisible unit (e.g. 'gram', 'piece')
            // cost_price / sale_price moved to product_variant_prices (history table)
            $table->integer('reorder_point')->default(0);    // Safety threshold in base units
            $table->json('attributes')->nullable();          // e.g. {"roast": "Medium"}
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['product_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
