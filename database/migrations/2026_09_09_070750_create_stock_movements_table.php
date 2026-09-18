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
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            // String + PHP backed enum (App\Enums\StockMovementType) instead DB enum()
            // easier future additions without schema alteration.
            $table->string('type');

            $table->integer('quantity'); // Signed, strictly in base units (+ in, - out)
            $table->string('unit_name_used'); // Packaging format label used during transaction
            $table->integer('unit_ratio_used')->default(1);
            $table->foreignId('related_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();

            // Polymorphic source — string support both bigint UUID/ULID-keyed models
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('reference_code')->nullable(); // e.g. 'DTR-20260908-XXXX'

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

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
