<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductVariant::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Warehouse::class)->constrained()->restrictOnDelete();
            $table->string('type');
            $table->integer('quantity');
            $table->string('unit_name_used');
            $table->integer('unit_ratio_used')->default(1);
            // Self-referential — keeps foreignId().
            $table->foreignId('related_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('reference_code')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_variant_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
