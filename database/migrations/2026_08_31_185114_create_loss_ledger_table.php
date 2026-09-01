<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('loss_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('transfer_requisitions');
            $table->foreignId('requisition_item_id')->constrained('transfer_requisition_items');
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->foreignId('warehouse_id')->constrained('warehouses'); // Location bearing the inventory loss
            $table->integer('lost_base_qty')->default(0);
            $table->integer('damaged_base_qty')->default(0);
            $table->decimal('unit_cost_price', 15, 4); // Snapshot of variant unit cost at incident time (precision)
            $table->decimal('total_financial_loss', 15, 4); // Computed cost multiplier (lost + damaged) * unit_cost_price
            $table->string('loss_category'); // E.g., "Damaged in Transit", "Short Shipment", "Warehouse Shrinkage"
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loss_ledgers');
    }
};
