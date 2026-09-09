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
        // Partial unique index — MySQL 8+/MariaDB 10.6+/PostgreSQL support this via a
        // functional/expression index or a filtered unique constraint.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX product_variant_prices_one_current_per_variant
                 ON product_variant_prices (product_variant_id) WHERE is_current = true'
            );
        } else {
            // MySQL/MariaDB: no native partial index support.
            // Emulate uniqueness with a generated column collapsing non-current rows to NULL
            // (NULLs are exempt from unique constraints).
            DB::statement(
                'ALTER TABLE product_variant_prices
                 ADD COLUMN current_variant_key BIGINT
                 GENERATED ALWAYS AS (CASE WHEN is_current = 1 THEN product_variant_id ELSE NULL END) STORED'
            );
            DB::statement(
                'CREATE UNIQUE INDEX product_variant_prices_one_current_per_variant
                 ON product_variant_prices (current_variant_key)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_prices');
    }
};
