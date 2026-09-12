<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Enums\RevisionStatus;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantUnitConversion;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

final class DemoWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedWarehouses();
        $this->seedUserWarehouseAssignments();
        $this->seedProductsAndVariants();
        $this->seedStockMovements();
        $this->seedTransferRequisitions();
        $this->seedInTransits();
        $this->seedLossLedgers();
    }

    private function seedWarehouses(): void
    {
        $warehouses = [
            ['code' => 'WH-MNL', 'name' => 'Manila Main Warehouse', 'location' => 'Manila, NCR'],
            ['code' => 'WH-CEB', 'name' => 'Cebu Branch Warehouse', 'location' => 'Cebu City, Cebu'],
            ['code' => 'WH-DVO', 'name' => 'Davao Branch Warehouse', 'location' => 'Davao City, Davao del Sur'],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(
                ['code' => $warehouse['code']],
                $warehouse,
            );
        }
    }

    private function seedUserWarehouseAssignments(): void
    {
        $warehouseMnl = Warehouse::where('code', 'WH-MNL')->first();
        $warehouseCeb = Warehouse::where('code', 'WH-CEB')->first();
        $warehouseDvo = Warehouse::where('code', 'WH-DVO')->first();

        if (! $warehouseMnl || ! $warehouseCeb || ! $warehouseDvo) {
            return;
        }

        $admin = User::where('email', 'admin@example.com')->first();
        $managerMnl = User::where('email', 'manager.mnl@example.com')->first();
        $managerCeb = User::where('email', 'manager.ceb@example.com')->first();
        $staffDvo = User::where('email', 'staff.dvo@example.com')->first();
        $auditor = User::where('email', 'auditor@example.com')->first();

        // Admin: all warehouses
        $admin?->warehouses()->sync([$warehouseMnl->id, $warehouseCeb->id, $warehouseDvo->id]);

        // Manila Manager: only WH-MNL
        $managerMnl?->warehouses()->sync([$warehouseMnl->id]);

        // Cebu Manager: only WH-CEB
        $managerCeb?->warehouses()->sync([$warehouseCeb->id]);

        // Davao Staff: only WH-DVO
        $staffDvo?->warehouses()->sync([$warehouseDvo->id]);

        // Auditor: all warehouses (read-only)
        $auditor?->warehouses()->sync([$warehouseMnl->id, $warehouseCeb->id, $warehouseDvo->id]);
    }

    private function seedProductsAndVariants(): void
    {
        $products = [
            ['name' => 'Arabica Specialty Coffee', 'category' => 'Coffee'],
            ['name' => 'Robusta Commercial Coffee', 'category' => 'Coffee'],
            ['name' => 'Premium Tea Blend', 'category' => 'Tea'],
        ];

        foreach ($products as $productData) {
            $product = Product::updateOrCreate(
                ['name' => $productData['name']],
                $productData,
            );

            // Each product gets 1–2 variants
            $variants = match ($product->name) {
                'Arabica Specialty Coffee' => [
                    [
                        'sku' => 'PROD-ARB-500G',
                        'name' => '500g Whole Bean',
                        'base_unit_name' => 'gram',
                        'reorder_point' => 5000,
                        'attributes' => ['roast' => 'Medium', 'origin' => 'Benguet'],
                    ],
                    [
                        'sku' => 'PROD-ARB-1KG',
                        'name' => '1kg Whole Bean',
                        'base_unit_name' => 'gram',
                        'reorder_point' => 3000,
                        'attributes' => ['roast' => 'Dark', 'origin' => 'Benguet'],
                    ],
                ],
                'Robusta Commercial Coffee' => [
                    [
                        'sku' => 'PROD-ROB-1KG',
                        'name' => '1kg Ground',
                        'base_unit_name' => 'gram',
                        'reorder_point' => 10000,
                        'attributes' => ['roast' => 'Dark', 'origin' => 'Liberica'],
                    ],
                ],
                'Premium Tea Blend' => [
                    [
                        'sku' => 'PROD-TEA-100B',
                        'name' => '100 Tea Bags',
                        'base_unit_name' => 'bag',
                        'reorder_point' => 200,
                        'attributes' => ['blend' => 'Green Earl Grey'],
                    ],
                ],
                default => [],
            };

            foreach ($variants as $variantData) {
                $variant = ProductVariant::updateOrCreate(
                    ['sku' => $variantData['sku']],
                    array_merge($variantData, ['product_id' => $product->id]),
                );

                $this->seedUnitConversions($variant);
                $this->seedCurrentPrice($variant);
            }
        }
    }

    private function seedUnitConversions(ProductVariant $variant): void
    {
        $conversions = match ($variant->sku) {
            'PROD-ARB-500G' => [
                ['unit_name' => 'Box', 'base_unit_ratio' => 12, 'is_default_purchase' => true, 'is_default_transfer' => true],
            ],
            'PROD-ARB-1KG' => [
                ['unit_name' => 'Box', 'base_unit_ratio' => 6, 'is_default_purchase' => true, 'is_default_transfer' => true],
                ['unit_name' => 'Case', 'base_unit_ratio' => 36, 'is_default_purchase' => false, 'is_default_transfer' => false],
            ],
            'PROD-ROB-1KG' => [
                ['unit_name' => 'Box', 'base_unit_ratio' => 20, 'is_default_purchase' => true, 'is_default_transfer' => true],
                ['unit_name' => 'Pallet', 'base_unit_ratio' => 200, 'is_default_purchase' => false, 'is_default_transfer' => false],
            ],
            'PROD-TEA-100B' => [
                ['unit_name' => 'Case', 'base_unit_ratio' => 10, 'is_default_purchase' => true, 'is_default_transfer' => true],
            ],
            default => [],
        };

        foreach ($conversions as $conversion) {
            ProductVariantUnitConversion::updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'unit_name' => $conversion['unit_name'],
                ],
                $conversion,
            );
        }
    }

    private function seedCurrentPrice(ProductVariant $variant): void
    {
        $prices = match ($variant->sku) {
            'PROD-ARB-500G' => ['cost_price' => 150.00, 'sale_price' => 280.00],
            'PROD-ARB-1KG' => ['cost_price' => 280.00, 'sale_price' => 520.00],
            'PROD-ROB-1KG' => ['cost_price' => 80.00, 'sale_price' => 150.00],
            'PROD-TEA-100B' => ['cost_price' => 120.00, 'sale_price' => 220.00],
            default => ['cost_price' => 50.00, 'sale_price' => 100.00],
        };

        // Only create if no current price exists
        if ($variant->currentPrice()->count() === 0) {
            ProductVariantPrice::create(array_merge($prices, [
                'product_variant_id' => $variant->id,
                'is_current' => true,
            ]));
        }
    }

    private function seedStockMovements(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $staffDvo = User::where('email', 'staff.dvo@example.com')->first();

        $variants = ProductVariant::with('unitConversions')->get();
        $warehouses = Warehouse::all();

        if ($variants->isEmpty() || $warehouses->isEmpty() || ! $admin) {
            return;
        }

        // Seed receive movements for each variant at WH-MNL
        foreach ($variants as $variant) {
            $unitConversion = $variant->unitConversions->first();
            StockMovement::updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $warehouses->first()->id,
                    'type' => MovementType::Receive->value,
                    'reference_code' => "RCV-{$variant->sku}",
                ],
                [
                    'quantity' => 100 * ($unitConversion?->base_unit_ratio ?? 1),
                    'unit_name_used' => $unitConversion?->unit_name ?? $variant->base_unit_name,
                    'unit_ratio_used' => $unitConversion?->base_unit_ratio ?? 1,
                    'created_by' => $admin->id,
                ],
            );
        }

        // Seed a ship movement for Arabica at WH-MNL
        $arbVariant = ProductVariant::where('sku', 'PROD-ARB-500G')->first();
        if ($arbVariant) {
            StockMovement::updateOrCreate(
                [
                    'product_variant_id' => $arbVariant->id,
                    'warehouse_id' => $warehouses->first()->id,
                    'type' => MovementType::Ship->value,
                    'reference_code' => "SHP-{$arbVariant->sku}-001",
                ],
                [
                    'quantity' => -2000,
                    'unit_name_used' => 'gram',
                    'unit_ratio_used' => 1,
                    'created_by' => $admin->id,
                ],
            );

            // TransferOut: Arabica leaving WH-MNL (source warehouse)
            $whMnl = Warehouse::where('code', 'WH-MNL')->first();
            if ($whMnl) {
                StockMovement::updateOrCreate(
                    [
                        'product_variant_id' => $arbVariant->id,
                        'warehouse_id' => $whMnl->id,
                        'type' => MovementType::TransferOut->value,
                        'reference_code' => 'TFR-OUT-PROD-ARB-500G',
                    ],
                    [
                        'quantity' => -12000,
                        'unit_name_used' => 'gram',
                        'unit_ratio_used' => 1,
                        'created_by' => $admin->id,
                    ],
                );
            }

            // TransferIn: Arabica arriving at WH-DVO (destination warehouse)
            $whDvo = Warehouse::where('code', 'WH-DVO')->first();
            if ($whDvo) {
                StockMovement::updateOrCreate(
                    [
                        'product_variant_id' => $arbVariant->id,
                        'warehouse_id' => $whDvo->id,
                        'type' => MovementType::TransferIn->value,
                        'reference_code' => 'TFR-IN-PROD-ARB-500G',
                    ],
                    [
                        'quantity' => 12000,
                        'unit_name_used' => 'gram',
                        'unit_ratio_used' => 1,
                        'created_by' => $admin->id,
                    ],
                );
            }
        }
    }

    private function seedTransferRequisitions(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $managerMnl = User::where('email', 'manager.mnl@example.com')->first();
        $staffDvo = User::where('email', 'staff.dvo@example.com')->first();

        $warehouses = Warehouse::all();

        if ($warehouses->count() < 2 || ! $admin) {
            return;
        }

        $fromWarehouse = $warehouses->first(); // WH-MNL
        $toWarehouse = $warehouses->last(); // WH-DVO (last seeded)

        // Transfer 1: Completed transfer from Manila to Davao
        $transfer1 = TransferRequisition::updateOrCreate(
            ['reference_code' => 'TR-2026-001'],
            [
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'status' => 'completed',
                'requested_by' => $admin->id,
                'approved_by' => $admin->id,
                'dispatched_by' => $managerMnl?->id ?? $admin->id,
                'received_by' => $staffDvo?->id ?? $admin->id,
                'requested_at' => now()->subDays(14),
                'approved_at' => now()->subDays(12),
                'dispatched_at' => now()->subDays(10),
                'completed_at' => now()->subDays(8),
            ],
        );

        // Transfer 2: Dispatched transfer from Manila to Cebu
        $toCebu = $warehouses->where('code', 'WH-CEB')->first();
        $transfer2 = null;
        if ($toCebu) {
            $transfer2 = TransferRequisition::updateOrCreate(
                ['reference_code' => 'TR-2026-002'],
                [
                    'from_warehouse_id' => $fromWarehouse->id,
                    'to_warehouse_id' => $toCebu->id,
                    'status' => 'dispatched',
                    'requested_by' => $admin->id,
                    'approved_by' => $admin->id,
                    'dispatched_by' => $managerMnl?->id ?? $admin->id,
                    'requested_at' => now()->subDays(5),
                    'approved_at' => now()->subDays(4),
                    'dispatched_at' => now()->subDays(2),
                ],
            );
        }

        $this->seedTransferRequisitionItems($transfer1);
        if ($transfer2) {
            $this->seedTransferRequisitionItems($transfer2);
        }

        // Create revisions for both transfers
        $this->seedRevisions($transfer1);
        if ($transfer2) {
            $this->seedRevisions($transfer2);
        }
    }

    private function seedTransferRequisitionItems(TransferRequisition $transfer): void
    {
        $variants = ProductVariant::with('unitConversions')->take(2)->get();

        foreach ($variants as $variant) {
            $unitConversion = $variant->unitConversions->first();
            $requestedQty = 10;
            $unitRatio = $unitConversion?->base_unit_ratio ?? 1;

            $item = TransferRequisitionItem::updateOrCreate(
                [
                    'transfer_requisition_id' => $transfer->id,
                    'product_variant_id' => $variant->id,
                ],
                [
                    'requested_unit_name' => $unitConversion?->unit_name ?? $variant->base_unit_name,
                    'requested_unit_ratio' => $unitRatio,
                    'requested_qty' => $requestedQty,
                    'requested_base_qty' => $requestedQty * $unitRatio,
                    'approved_unit_name' => $unitConversion?->unit_name ?? $variant->base_unit_name,
                    'approved_unit_ratio' => $unitRatio,
                    'approved_qty' => $requestedQty,
                    'approved_base_qty' => $requestedQty * $unitRatio,
                    'shipped_base_qty' => $requestedQty * $unitRatio,
                    'received_good_base_qty' => $requestedQty * $unitRatio,
                    'received_qty' => $requestedQty,
                ],
            );

            $this->seedRevision($item, $variant);
        }
    }

    private function seedRevisions(TransferRequisition $transfer): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            return;
        }

        foreach ($transfer->items as $item) {
            $variant = $item->productVariant;

            // Create an accepted revision proposal
            TransferRequisitionItemRevision::updateOrCreate(
                [
                    'transfer_requisition_item_id' => $item->id,
                    'product_variant_id' => $variant->id,
                    'side' => 'fulfiller',
                ],
                [
                    'user_id' => $admin->id,
                    'proposed_unit_name' => $item->approved_unit_name,
                    'proposed_qty' => $item->approved_qty,
                    'proposed_base_qty' => $item->approved_base_qty,
                    'negotiation_reason' => 'Initial fulfiller proposal',
                    'status' => RevisionStatus::Accepted->value,
                    'responded_at' => now(),
                ],
            );
        }
    }

    private function seedRevision(TransferRequisitionItem $item, ProductVariant $variant): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            return;
        }

        // Create a pending revision proposal
        TransferRequisitionItemRevision::updateOrCreate(
            [
                'transfer_requisition_item_id' => $item->id,
                'product_variant_id' => $variant->id,
                'side' => 'fulfiller',
            ],
            [
                'user_id' => $admin->id,
                'proposed_unit_name' => $item->approved_unit_name,
                'proposed_qty' => $item->approved_qty,
                'proposed_base_qty' => $item->approved_base_qty,
                'negotiation_reason' => 'Initial fulfiller proposal',
                'status' => RevisionStatus::Accepted->value,
                'responded_at' => now(),
            ],
        );
    }

    private function seedInTransits(): void
    {
        $transfers = TransferRequisition::whereIn('reference_code', ['TR-2026-001', 'TR-2026-002'])->get();

        foreach ($transfers as $transfer) {
            $variant = ProductVariant::first();
            $item = $transfer->items()->first();

            if (! $variant || ! $item) {
                continue;
            }

            $status = $transfer->status === 'completed' ? 'cleared' : 'in_transit';

            InTransit::updateOrCreate(
                [
                    'transfer_requisition_id' => $transfer->id,
                    'transfer_requisition_item_id' => $item->id,
                    'product_variant_id' => $variant->id,
                ],
                [
                    'dispatched_base_qty' => $item->approved_base_qty,
                    'dispatched_at' => $transfer->dispatched_at ?? now()->subDays(2),
                    'status' => $status,
                ],
            );
        }
    }

    private function seedLossLedgers(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $warehouse = Warehouse::first();
        $variant = ProductVariant::first();

        if (! $admin || ! $warehouse || ! $variant) {
            return;
        }

        $transfers = TransferRequisition::whereIn('reference_code', ['TR-2026-001', 'TR-2026-002'])->get();

        foreach ($transfers as $transfer) {
            $item = $transfer->items()->first();

            // Loss ledger for each transfer (shortfall of 200 grams)
            LossLedger::updateOrCreate(
                [
                    'transfer_requisition_id' => $transfer->id,
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'transfer_requisition_item_id' => $item?->id,
                    'lost_base_qty' => 200,
                    'damaged_base_qty' => 0,
                    'unit_cost_price' => 0.15,
                    'total_financial_loss' => 30.00,
                    'loss_category' => 'shortfall',
                    'recorded_by' => $admin->id,
                ],
            );
        }
    }
}