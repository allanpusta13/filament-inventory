<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->integer('on_hand_quantity')->default(0);  // Physical stock in Base Units
            $table->integer('reserved_quantity')->default(0); // Allocated stock locked for confirmed transfers
            $table->timestamps();

            // 🛡️ Concurrency Race Condition Guard: Composite Unique Key
            $table->unique(['variant_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock');
    }
};
