<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique(); // Unique business key
            $table->foreignId('from_warehouse_id')->constrained('warehouses'); // Fulfiller Location
            $table->foreignId('to_warehouse_id')->constrained('warehouses');   // Requestor Location
            $table->enum('status', [
                'draft', 'requested', 'under_review_fulfiller', 'under_review_requestor',
                'confirmed', 'dispatched', 'partially_received', 'completed',
                'closed_with_loss', 'cancelled',
            ])->default('draft');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('dispatched_by')->nullable()->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes(); // Enable Soft-Deletes for proper trashing & restoration safety
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requisitions');
    }
};
