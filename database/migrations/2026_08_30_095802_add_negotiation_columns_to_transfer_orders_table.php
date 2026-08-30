<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_orders', function (Blueprint $table) {
            $table->string('driver_name')->nullable()->after('notes');
            $table->string('vehicle_plate')->nullable()->after('driver_name');
        });
    }
};
