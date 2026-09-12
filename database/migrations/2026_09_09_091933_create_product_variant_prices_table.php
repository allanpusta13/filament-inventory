<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();

            $table->decimal('cost_price', 15, 4)->default(0.0000);
            $table->decimal('sale_price', 15, 4)->default(0.0000);

            $table->timestamp('effective_from')->useCurrent();
            $table->boolean('is_current')->default(true);

            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['product_variant_id', 'effective_from']);
        });

        // Unique index: only one current price per variant
        // Application-level enforcement via ProductVariantPrice::recordNewPrice()
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX product_variant_prices_one_current_per_variant
                 ON product_variant_prices (product_variant_id) WHERE is_current = true'
            );
        } elseif (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite supports partial unique index
            DB::statement(
                'CREATE UNIQUE INDEX product_variant_prices_one_current_per_variant
                 ON product_variant_prices (product_variant_id) WHERE is_current = 1'
            );
        } else {
            // MySQL: rely on application-level enforcement
            // ProductVariantPrice::recordNewPrice() unsets previous current before creating new
            // Add a regular index for query performance on current prices
            DB::statement(
                'CREATE INDEX product_variant_prices_is_current_idx
                 ON product_variant_prices (product_variant_id, is_current)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_prices');
    }
};
