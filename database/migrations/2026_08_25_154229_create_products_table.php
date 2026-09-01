<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique(); // Base Product SKU
            $table->string('name');          // Product family name
            $table->string('category')->nullable();
            $table->integer('reorder_point')->default(0); // Default threshold in Base Units
            $table->softDeletes();           // Enforce Soft-Deletes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
