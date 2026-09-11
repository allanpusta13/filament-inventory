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
            $table->foreignId('transfer_requisition_item_id');
            $table->foreign('transfer_requisition_item_id', 'tri_rev_item_fk')->references('id')->on('transfer_requisition_items')->onDelete('cascade');
            $table->foreignId('user_id');
            $table->foreign('user_id', 'tri_rev_user_fk')->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('product_variant_id');
            $table->foreign('product_variant_id', 'tri_rev_variant_fk')->references('id')->on('product_variants')->onDelete('restrict');
            $table->foreignId('substitute_product_variant_id')->nullable();
            $table->foreign('substitute_product_variant_id', 'tri_rev_sub_variant_fk')->references('id')->on('product_variants')->onDelete('restrict');

            $table->string('proposed_unit_name');
            $table->integer('proposed_unit_ratio')->default(1);
            $table->integer('proposed_qty');
            $table->integer('proposed_base_qty');
            $table->text('negotiation_reason')->nullable();

            // Negotiation tracking
            // String + PHP backed enum (App\Enums\NegotiationSide) - which party proposed this revision.
            $table->string('side');

            // String + PHP backed enum (App\Enums\RevisionStatus) - outcome of this specific proposal.
            $table->string('status')->default('pending');

            // Self-reference: the revision this one is countering/responding to, if any.
            // Null means this is the opening proposal in the thread for this item.
            $table->foreignId('responds_to_revision_id')->nullable();
            $table->foreign('responds_to_revision_id', 'tri_rev_parent_fk')->references('id')->on('transfer_requisition_item_revisions')->onDelete('set null');

            $table->timestamp('responded_at')->nullable(); // When status moved out of pending

            $table->timestamps();

            $table->index('transfer_requisition_item_id', 'tri_rev_item_idx');
            $table->index(['transfer_requisition_item_id', 'status'], 'tri_rev_item_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requisition_item_revisions');
    }
};
