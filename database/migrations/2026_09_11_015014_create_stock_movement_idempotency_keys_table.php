<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_requisition_id')
                ->constrained('transfer_requisitions')
                ->cascadeOnDelete();
            $table->string('payload_checksum', 64);
            $table->json('resulting_item_states');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['transfer_requisition_id', 'payload_checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_idempotency_keys');
    }
};
