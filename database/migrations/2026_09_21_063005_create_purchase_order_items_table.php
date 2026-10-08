<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PurchaseOrder::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ProductVariant::class)->constrained()->restrictOnDelete();
            $table->string('ordered_unit_name');
            $table->integer('ordered_unit_ratio');
            $table->integer('ordered_qty');
            $table->integer('ordered_base_qty');
            $table->decimal('unit_cost_price', 15, 4)->default(0.0000);
            $table->integer('received_base_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
