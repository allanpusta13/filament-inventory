<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requisition_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->json('changes_payload');
            $table->timestamps();

            $table->index(['transfer_requisition_id', 'created_at'], 'trq_audits_trq_id_created_idx');
        });
    }
};
