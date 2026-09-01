<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('in_transit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('transfer_requisitions')->onDelete('cascade');
            $table->foreignId('requisition_item_id')->constrained('transfer_requisition_items')->onDelete('cascade');
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->integer('dispatched_base_qty'); // Expected Base quantity traveling
            $table->integer('received_base_qty')->default(0); // Actual received good quantity
            $table->integer('received_damaged_base_qty')->default(0); // Actual received damaged quantity
            $table->timestamp('dispatched_at');
            $table->timestamp('received_at')->nullable();
            $table->enum('status', ['in_transit', 'partially_received', 'received', 'cleared'])->default('in_transit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_transit');
    }
};
