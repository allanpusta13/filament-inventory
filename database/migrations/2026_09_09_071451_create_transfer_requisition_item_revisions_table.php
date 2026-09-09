<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requisition_item_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_requisition_item_id')
                ->constrained('transfer_requisition_items', indexName: 'tr_item_rev_tr_item_id_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->foreignId('product_variant_id')
                ->constrained('product_variants', indexName: 'tr_item_rev_prod_var_fk');

            $table->foreignId('substitute_product_variant_id')->nullable()
                ->constrained('product_variants', indexName: 'tr_item_rev_sub_prod_var_fk');

            $table->string('proposed_unit_name');
            $table->integer('proposed_qty');
            $table->integer('proposed_base_qty');
            $table->text('negotiation_reason')->nullable();

            // Negotiation tracking
            $table->string('side');
            $table->string('status')->default('pending');

            // Self-reference
            $table->foreignId('responds_to_revision_id')->nullable()
                ->constrained('transfer_requisition_item_revisions', indexName: 'tr_item_rev_responds_to_fk')
                ->nullOnDelete();

            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // Custom index names (prevents 1059 errors on index generation)
            $table->index('transfer_requisition_item_id', 'tr_item_rev_item_idx');
            $table->index(['transfer_requisition_item_id', 'status'], 'tr_item_rev_item_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requisition_item_revisions');
    }
};
