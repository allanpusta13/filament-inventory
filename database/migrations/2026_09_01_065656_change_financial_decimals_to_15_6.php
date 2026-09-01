<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 6)->change();
            $table->decimal('sale_price', 15, 6)->change();
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->decimal('price', 15, 6)->change();
        });

        Schema::table('loss_ledgers', function (Blueprint $table) {
            $table->decimal('unit_cost_price', 15, 6)->change();
            $table->decimal('total_financial_loss', 15, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 4)->change();
            $table->decimal('sale_price', 15, 4)->change();
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->decimal('price', 15, 4)->change();
        });

        Schema::table('loss_ledgers', function (Blueprint $table) {
            $table->decimal('unit_cost_price', 15, 4)->change();
            $table->decimal('total_financial_loss', 15, 4)->change();
        });
    }
};
