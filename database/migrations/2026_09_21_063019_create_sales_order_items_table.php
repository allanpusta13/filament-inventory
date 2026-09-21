<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('unit_name');
            $table->integer('unit_ratio')->default(1);
            $table->integer('qty');
            $table->integer('base_qty');
            $table->decimal('unit_sale_price_snapshot', 15, 4)->default('0.0000');
            $table->integer('dispatched_base_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sales_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
