<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('ordered_unit_name');
            $table->integer('ordered_unit_ratio')->default(1);
            $table->integer('ordered_qty');
            $table->integer('ordered_base_qty');
            $table->decimal('unit_cost_price', 15, 4)->default('0.0000');
            $table->integer('received_base_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
        });
    }
};
