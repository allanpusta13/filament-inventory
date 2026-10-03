<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TransferRequisition::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ProductVariant::class)->constrained()->restrictOnDelete();
            // Substitute is a custom column targeting product_variants.
            $table->foreignId('substitute_product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->string('requested_unit_name');
            $table->integer('requested_unit_ratio');
            $table->integer('requested_qty');
            $table->integer('requested_base_qty');
            $table->string('approved_unit_name')->nullable();
            $table->integer('approved_unit_ratio')->nullable();
            $table->integer('approved_qty')->nullable();
            $table->integer('approved_base_qty')->nullable();
            $table->integer('shipped_base_qty')->default(0);
            $table->integer('received_good_base_qty')->default(0);
            $table->integer('received_damaged_base_qty')->default(0);
            $table->integer('received_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('transfer_requisition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requisition_items');
    }
};
