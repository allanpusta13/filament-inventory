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
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('sku')->unique(); // Variant-specific SKU (e.g., PROD-COF-001-L)
            $table->string('barcode')->nullable()->unique(); // Scanner optimization
            $table->string('name');          // E.g., "Red / Large", "500ml Bottle"
            $table->json('attributes')->nullable(); // Attributes block (e.g., {"color": "Red", "size": "L"})
            $table->string('base_unit_name'); // Smallest non-divisible unit (e.g., "gram", "piece")
            $table->json('images')->nullable();
            $table->decimal('cost_price', 15, 4); // Cost price per Base Unit (micro-precision)
            $table->decimal('sale_price', 15, 4); // Sale price per Base Unit (micro-precision)
            $table->softDeletes();           // Enforce Soft-Deletes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
