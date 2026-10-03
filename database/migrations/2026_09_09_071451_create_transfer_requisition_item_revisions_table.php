<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requisition_item_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TransferRequisitionItem::class)->constrained(indexName: 'tri_revisions_tri_id_foreign')->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained();
            $table->foreignIdFor(ProductVariant::class)->constrained(indexName: 'trirs_pv_id_foreign')->restrictOnDelete();
            $table->foreignId('substitute_product_variant_id')->nullable()->constrained('product_variants', indexName: 'trirs_substitute_pv_id_foreign')->restrictOnDelete();
            $table->string('proposed_unit_name');
            $table->integer('proposed_unit_ratio');
            $table->integer('proposed_qty');
            $table->integer('proposed_base_qty');
            $table->text('negotiation_reason')->nullable();
            $table->string('side');
            $table->string('status')->default('pending');
            // Self-referential chain — keeps foreignId().
            $table->foreignId('responds_to_revision_id')->nullable()->constrained('transfer_requisition_item_revisions', indexName: 'trirr_to_id_foreign')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index('transfer_requisition_item_id', 'trid');
            $table->index(['transfer_requisition_item_id', 'status'], 'trid_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requisition_item_revisions');
    }
};
