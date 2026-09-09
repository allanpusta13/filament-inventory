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
            $table->foreignId('transfer_requisition_item_id')->constrained('transfer_requisition_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->foreignId('substitute_product_variant_id')->nullable()->constrained('product_variants');

            $table->string('proposed_unit_name');
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
            $table->foreignId('responds_to_revision_id')->nullable()
                ->constrained('transfer_requisition_item_revisions')->nullOnDelete();

            $table->timestamp('responded_at')->nullable(); // When status moved out of pending

            $table->timestamps();

            $table->index('transfer_requisition_item_id');
            $table->index(['transfer_requisition_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requisition_item_revisions');
    }
};
