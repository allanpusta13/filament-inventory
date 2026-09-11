<?php

declare(strict_types=1);

use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('in_transits', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TransferRequisition::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(TransferRequisitionItem::class)->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();

            $table->integer('dispatched_base_qty');
            $table->timestamp('dispatched_at');

            // String + PHP backed enum (App\Enums\InTransitStatus) instead of DB enum().
            $table->string('status')->default('in_transit');

            $table->timestamps();

            $table->index(['transfer_requisition_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_transits');
    }
};
