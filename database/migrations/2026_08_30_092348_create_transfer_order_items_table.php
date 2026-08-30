<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('requested_quantity')->default(0);
            $table->integer('approved_quantity')->nullable();
            $table->integer('received_quantity')->nullable();
            $table->integer('damaged_quantity')->default(0);
            $table->string('item_status')->default('requested');
            $table->foreignId('added_by_branch_id')->nullable()->constrained('warehouses');
            $table->text('variance_reason')->nullable();
            $table->timestamps();

            $table->index('transfer_order_id');
            $table->index('product_id');
        });
    }
};
