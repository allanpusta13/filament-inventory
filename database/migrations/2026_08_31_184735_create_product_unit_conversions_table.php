<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('product_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->string('unit_name'); // Packaging name (e.g., "Box", "Bag", "Pallet")
            $table->integer('base_unit_ratio'); // Conversion multiplier (e.g., 1 Box = 24 Pcs -> 24)
            $table->boolean('is_default_purchase')->default(false);
            $table->boolean('is_default_transfer')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_unit_conversions');
    }
};
