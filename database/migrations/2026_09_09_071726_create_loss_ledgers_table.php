<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('loss_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TransferRequisition::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(TransferRequisitionItem::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ProductVariant::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Warehouse::class)->constrained()->restrictOnDelete();
            $table->integer('lost_base_qty')->default(0);
            $table->integer('damaged_base_qty')->default(0);
            $table->decimal('unit_cost_price', 15, 4);
            $table->decimal('total_financial_loss', 15, 4);
            $table->string('loss_category')->default('shortfall');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['warehouse_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loss_ledgers');
    }
};
