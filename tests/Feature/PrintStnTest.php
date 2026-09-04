<?php

declare(strict_types=1);

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

it('can render the STN manifest view', function () {
    expect(View::exists('pdf.stn-manifest'))->toBeTrue();
});

it('uses DomPDF facade', function () {
    expect(class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf'))->toBeTrue();
});

it('renders reference code, warehouses, and items in the STN manifest', function () {
    $requester = User::factory()->create();
    $fromWarehouse = App\Models\Warehouse::factory()->create(['name' => 'Manila Warehouse', 'code' => 'WH-MNL']);
    $toWarehouse = App\Models\Warehouse::factory()->create(['name' => 'Cebu Warehouse', 'code' => 'WH-CEB']);

    $requisition = TransferRequisition::factory()->create([
        'reference_code' => 'STN-TEST-001',
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'status' => TransferRequisitionStatus::Dispatched,
        'requested_by' => $requester->id,
        'dispatched_at' => now()->subDay(),
        'completed_at' => now(),
        'notes' => 'Urgent restock',
    ]);

    $variant = App\Models\ProductVariant::factory()->create([
        'sku' => 'SKU-TEST-001',
        'name' => 'Test Widget',
        'base_unit_name' => 'piece',
    ]);

    TransferRequisitionItem::factory()->create([
        'requisition_id' => $requisition->id,
        'variant_id' => $variant->id,
        'requested_unit_name' => 'piece',
        'requested_unit_ratio' => 1,
        'requested_qty' => 25,
        'requested_base_qty' => 25,
        'approved_unit_name' => 'piece',
        'approved_unit_ratio' => 1,
        'approved_qty' => 20,
        'approved_base_qty' => 20,
        'shipped_base_qty' => 20,
    ]);

    $requisition->load(['requestedBy', 'fromWarehouse', 'toWarehouse', 'items.variant']);

    $html = View::make('pdf.stn-manifest', [
        'requisition' => $requisition,
        'qrCode' => '<svg width="140" height="140"><circle cx="70" cy="70" r="60"/></svg>',
    ])->render();

    $this->assertStringContainsString('Stock Transfer Note', $html);
    $this->assertStringContainsString('STN-TEST-001', $html);
    $this->assertStringContainsString('Manila Warehouse', $html);
    $this->assertStringContainsString('WH-MNL', $html);
    $this->assertStringContainsString('Cebu Warehouse', $html);
    $this->assertStringContainsString('WH-CEB', $html);
    $this->assertStringContainsString('SKU-TEST-001', $html);
    $this->assertStringContainsString('Test Widget', $html);
    $this->assertStringContainsString('20', $html);
    $this->assertStringContainsString(TransferRequisitionStatus::Dispatched->getLabel(), $html);
    $this->assertStringContainsString('Urgent restock', $html);
});

it('renders empty items table when no items', function () {
    $requester = User::factory()->create();
    $fromWarehouse = App\Models\Warehouse::factory()->create();
    $toWarehouse = App\Models\Warehouse::factory()->create();

    $requisition = TransferRequisition::factory()->create([
        'reference_code' => 'STN-EMPTY-001',
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'requested_by' => $requester->id,
    ]);

    $requisition->load(['requestedBy', 'fromWarehouse', 'toWarehouse', 'items.variant']);

    $html = View::make('pdf.stn-manifest', [
        'requisition' => $requisition,
        'qrCode' => null,
    ])->render();

    $this->assertStringContainsString('STN-EMPTY-001', $html);
    $this->assertStringContainsString('No items', $html);
    $this->assertStringContainsString('Origin Warehouse Manager', $html);
    $this->assertStringContainsString('Destination Warehouse Manager', $html);
});

it('renders requested and approved quantities correctly', function () {
    $requester = User::factory()->create();
    $fromWarehouse = App\Models\Warehouse::factory()->create();
    $toWarehouse = App\Models\Warehouse::factory()->create();

    $requisition = TransferRequisition::factory()->create([
        'reference_code' => 'STN-QTY-001',
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'status' => TransferRequisitionStatus::Completed,
        'requested_by' => $requester->id,
        'requested_at' => now()->subDays(5),
        'dispatched_at' => now()->subDays(2),
        'completed_at' => now(),
    ]);

    $variant = App\Models\ProductVariant::factory()->create([
        'sku' => 'SKU-QTY-001',
        'name' => 'Qty Test Item',
        'base_unit_name' => 'box',
    ]);

    TransferRequisitionItem::factory()->create([
        'requisition_id' => $requisition->id,
        'variant_id' => $variant->id,
        'requested_unit_name' => 'box',
        'requested_unit_ratio' => 12,
        'requested_qty' => 10,
        'requested_base_qty' => 120,
        'approved_unit_name' => 'box',
        'approved_unit_ratio' => 12,
        'approved_qty' => 8,
        'approved_base_qty' => 96,
        'shipped_base_qty' => 96,
    ]);

    $requisition->load(['requestedBy', 'fromWarehouse', 'toWarehouse', 'items.variant']);

    $html = View::make('pdf.stn-manifest', [
        'requisition' => $requisition,
        'qrCode' => null,
    ])->render();

    $this->assertStringContainsString('120', $html);
    $this->assertStringContainsString('96', $html);
    $this->assertStringContainsString('box', $html);
    $this->assertStringContainsString(TransferRequisitionStatus::Completed->getLabel(), $html);
});

it('renders requestedBy and requested_at info row', function () {
    $requester = User::factory()->create(['name' => 'Juan Dela Cruz']);
    $fromWarehouse = App\Models\Warehouse::factory()->create();
    $toWarehouse = App\Models\Warehouse::factory()->create();

    $requisition = TransferRequisition::factory()->create([
        'reference_code' => 'STN-INFO-001',
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'requested_by' => $requester->id,
        'requested_at' => now()->subDays(3),
    ]);

    $requisition->load(['requestedBy', 'fromWarehouse', 'toWarehouse', 'items.variant']);

    $html = View::make('pdf.stn-manifest', [
        'requisition' => $requisition,
        'qrCode' => null,
    ])->render();

    $this->assertStringContainsString('Juan Dela Cruz', $html);
    $this->assertStringContainsString('Requested by', $html);
    $this->assertStringContainsString('Requested at', $html);
});
