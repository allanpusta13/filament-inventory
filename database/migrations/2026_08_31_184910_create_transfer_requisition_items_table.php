<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('transfer_requisitions')->onDelete('cascade');
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->string('requested_unit_name');
            $table->integer('requested_unit_ratio');
            $table->integer('requested_qty');
            $table->integer('requested_base_qty'); // Base unit conversion cache at request
            $table->string('approved_unit_name')->nullable();
            $table->integer('approved_unit_ratio')->nullable();
            $table->integer('approved_qty')->nullable();
            $table->integer('approved_base_qty')->nullable(); // Base unit conversion cache after negotiation
            $table->integer('shipped_base_qty')->default(0);
            $table->integer('received_good_base_qty')->default(0);
            $table->integer('received_damaged_base_qty')->default(0);
            $table->foreignId('substitute_variant_id')->nullable()->constrained('product_variants'); // 🛡️ Substitute Variant bypass
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requisition_items');
    }
};
