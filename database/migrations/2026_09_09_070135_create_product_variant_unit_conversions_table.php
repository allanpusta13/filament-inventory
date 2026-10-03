<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductVariant::class)->constrained()->cascadeOnDelete();
            $table->string('unit_name');
            $table->integer('base_unit_ratio');
            $table->boolean('is_default_purchase')->default(false);
            $table->boolean('is_default_transfer')->default(false);
            $table->timestamps();

            $table->unique(['product_variant_id', 'unit_name'], 'product_variant_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_unit_conversions');
    }
};
