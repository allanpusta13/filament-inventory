<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['receive', 'ship', 'transfer_out', 'transfer_in', 'adjustment']);
            $table->integer('quantity');
            $table->foreignId('related_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id']);
            $table->index('created_by');
            $table->index('warehouse_id');
            $table->index('type');
        });
    }
};
