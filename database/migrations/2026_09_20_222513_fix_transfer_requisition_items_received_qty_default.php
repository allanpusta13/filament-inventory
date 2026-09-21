<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_requisition_items', function (Blueprint $table) {
            $table->integer('received_qty')->default(0)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transfer_requisition_items', function (Blueprint $table) {
            $table->integer('received_qty')->nullable()->default(null)->change();
        });
    }
};
