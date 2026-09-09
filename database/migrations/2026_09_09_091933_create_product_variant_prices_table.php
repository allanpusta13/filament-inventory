<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->boolean('is_current')->default(true); // App is responsible for flipping the prior row to false

            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable(); // e.g. reason for price change

            $table->timestamps();

            $table->index(['product_variant_id', 'effective_from']);
        });

        // Enforce at most one current price row per variant, as a safety net
        // against application bugs that fail to unset the previous is_current flag.
        // MySQL 8.4+ stricter generated-column validation makes the generated-column
        // emulation fragile; enforce at the application level instead (see model scope).
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_prices');
    }
};
