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
            $table->foreignId('transfer_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_requisition_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();

            $table->integer('lost_base_qty')->default(0);
            $table->integer('damaged_base_qty')->default(0);
            $table->decimal('unit_cost_price', 15, 4);      // Snapshot of variant unit cost at incident time
            $table->decimal('total_financial_loss', 15, 4); // Total financial write-off

            $table->string('loss_category')->default('shortfall');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['warehouse_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loss_ledgers');
    }
};
