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

            // Custom short name to avoid MySQL 64-char identifier limit
            $table->unique(['transfer_requisition_id', 'payload_checksum'], 'skmik_uniq_req_payload');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_idempotency_keys');
    }
};