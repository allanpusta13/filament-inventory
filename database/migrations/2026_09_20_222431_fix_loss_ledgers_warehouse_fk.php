<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // FK already created in create_loss_ledgers_table with constrained()->restrictOnDelete()
        // This migration is kept for historical record but performs no action
    }

    public function down(): void
    {
        // No-op - FK created by original migration
    }
};