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
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->enum('type', [
                'receive', 'ship', 'transfer_out', 'transfer_in', 
                'transit_out', 'transit_in', 'adjustment', 'loss'
            ]);
            $table->integer('quantity');
$table->string('unit_name_used')->default('Base Unit');
$table->integer('unit_ratio_used')->default(1);
            $table->foreignId('related_movement_id')->nullable()->constrained('stock_movements')->onDelete('set null');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_code')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['variant_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
