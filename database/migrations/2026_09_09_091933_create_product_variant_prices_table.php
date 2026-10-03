<?php

declare(strict_types=1);

use App\Models\ProductVariant;
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
            $table->foreignIdFor(ProductVariant::class)->constrained()->cascadeOnDelete();
            $table->decimal('cost_price', 15, 4)->default(0.0000);
            $table->decimal('sale_price', 15, 4)->default(0.0000);
            $table->timestamp('effective_from')->useCurrent();
            $table->boolean('is_current')->default(true);
            // set_by is a non-conventional User FK — keeps foreignId().
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_variant_id', 'effective_from']);
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX product_variant_prices_current_unique ON product_variant_prices (product_variant_id) WHERE is_current = true'
            );
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_prices');
    }
};
