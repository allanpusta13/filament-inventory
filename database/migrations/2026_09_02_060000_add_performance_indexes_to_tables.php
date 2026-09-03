<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index('warehouse_id');
            $table->index(['warehouse_id', 'type', 'created_at']);
            $table->index(['warehouse_id', 'created_at']);
            $table->index('created_by');
        });

        Schema::table('warehouse_stock', function (Blueprint $table): void {
            $table->index('warehouse_id');
        });

        Schema::table('transfer_requisitions', function (Blueprint $table): void {
            $table->index('from_warehouse_id');
            $table->index('to_warehouse_id');
            $table->index('status');
            $table->index(['status', 'requested_at']);
            $table->index('requested_by');
        });

        Schema::table('transfer_requisition_items', function (Blueprint $table): void {
            $table->index('requisition_id');
            $table->index('variant_id');
        });

        Schema::table('in_transit', function (Blueprint $table): void {
            $table->index('status');
            $table->index('requisition_id');
            $table->index('requisition_item_id');
        });

        Schema::table('loss_ledgers', function (Blueprint $table): void {
            $table->index('requisition_id');
            $table->index('warehouse_id');
        });

        Schema::table('product_prices', function (Blueprint $table): void {
            $table->index('variant_id');
            $table->index(['variant_id', 'warehouse_id']);
        });

        Schema::table('product_unit_conversions', function (Blueprint $table): void {
            $table->index('variant_id');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->index('product_id');
        });

        Schema::table('transfer_order_items', function (Blueprint $table): void {
            $table->index('item_status');
            $table->index('added_by_branch_id');
        });

        Schema::table('transfer_orders', function (Blueprint $table): void {
            $table->index('dispatched_by');
            $table->index('received_by');
        });

        Schema::table('transfer_order_audits', function (Blueprint $table): void {
            $table->index('user_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['warehouse_id', 'created_at']);
            $table->dropIndex(['warehouse_id', 'type', 'created_at']);
            $table->dropIndex(['warehouse_id']);
        });

        Schema::table('warehouse_stock', function (Blueprint $table): void {
            $table->dropIndex(['warehouse_id']);
        });

        Schema::table('transfer_requisitions', function (Blueprint $table): void {
            $table->dropIndex(['requested_by']);
            $table->dropIndex(['status', 'requested_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['to_warehouse_id']);
            $table->dropIndex(['from_warehouse_id']);
        });

        Schema::table('transfer_requisition_items', function (Blueprint $table): void {
            $table->dropIndex(['variant_id']);
            $table->dropIndex(['requisition_id']);
        });

        Schema::table('in_transit', function (Blueprint $table): void {
            $table->dropIndex(['requisition_item_id']);
            $table->dropIndex(['requisition_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('loss_ledgers', function (Blueprint $table): void {
            $table->dropIndex(['warehouse_id']);
            $table->dropIndex(['requisition_id']);
        });

        Schema::table('product_prices', function (Blueprint $table): void {
            $table->dropIndex(['variant_id', 'warehouse_id']);
            $table->dropIndex(['variant_id']);
        });

        Schema::table('product_unit_conversions', function (Blueprint $table): void {
            $table->dropIndex(['variant_id']);
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex(['product_id']);
        });

        Schema::table('transfer_order_items', function (Blueprint $table): void {
            $table->dropIndex(['added_by_branch_id']);
            $table->dropIndex(['item_status']);
        });

        Schema::table('transfer_orders', function (Blueprint $table): void {
            $table->dropIndex(['received_by']);
            $table->dropIndex(['dispatched_by']);
        });

        Schema::table('transfer_order_audits', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
        });
    }
};
