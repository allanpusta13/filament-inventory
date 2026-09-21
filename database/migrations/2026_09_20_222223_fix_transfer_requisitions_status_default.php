<?php

declare(strict_types=1);

use App\Enums\TransferRequisitionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_requisitions', function (Blueprint $table) {
            $table->string('status')->default(TransferRequisitionStatus::Draft->value)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transfer_requisitions', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }
};
