<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §2.21 — `direct_transfers.transferred_by` is nullable + `nullOnDelete`.
 *
 * The historic create-table migration declared it NOT NULL + `restrictOnDelete`,
 * which contradicted the approved blueprint (§2.21 table and the §2.21 canonical
 * exemplar). This additive migration brings the column in line without editing
 * the historic migration.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('direct_transfers', function (Blueprint $table) {
            $table->dropForeign(['transferred_by']);
        });

        Schema::table('direct_transfers', function (Blueprint $table) {
            $table->foreignId('transferred_by')->nullable()->change();
            $table->foreign('transferred_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('direct_transfers', function (Blueprint $table) {
            $table->dropForeign(['transferred_by']);
        });

        Schema::table('direct_transfers', function (Blueprint $table) {
            $table->foreignId('transferred_by')->nullable(false)->change();
            $table->foreign('transferred_by')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
