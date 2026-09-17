<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\TransferRequisitions\Pages\ListTransferRequisitions;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    TransferRequisition::truncate();
    TransferRequisitionItem::truncate();
    InTransit::truncate();
    LossLedger::truncate();
    StockMovement::truncate();
    Warehouse::truncate();
    User::truncate();
    ProductVariant::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->fulfiller = User::factory()->warehouseStaff()->create();
    $this->requestor = User::factory()->warehouseStaff()->create();

    $this->fromWarehouse = Warehouse::factory()->create(['name' => 'Origin Warehouse', 'code' => 'ORG']);
    $this->toWarehouse = Warehouse::factory()->create(['name' => 'Dest Warehouse', 'code' => 'DST']);

    $this->actingAs($this->admin);
});

describe('recordLoss action', function () {
    it('can record loss for dispatched requisition', function () {
        $requisition = TransferRequisition::factory()->create([
            'status' => TransferRequisitionStatus::Dispatched,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $variant = ProductVariant::factory()->create();
        TransferRequisitionItem::factory()->for($requisition)->create([
            'product_variant_id' => $variant->id,
            'shipped_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => $variant->base_unit_name,
            'approved_unit_ratio' => 1,
        ]);

        InTransit::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'transfer_requisition_item_id' => $requisition->items->first()->id,
            'product_variant_id' => $variant->id,
            'dispatched_base_qty' => 100,
            'status' => InTransitStatus::InTransit,
        ]);

        livewire(ListTransferRequisitions::class)
            ->loadTable()
            ->callAction(TestAction::make('recordLoss')->table($requisition), [
                'product_variant_id' => $variant->id,
                'loss_category' => 'shortfall',
                'lost_base_qty' => 10,
                'damaged_base_qty' => 5,
                'total_financial_loss' => 150.00,
                'notes' => 'Damaged during transit',
            ])
            ->assertNotified();

        assertDatabaseHas(LossLedger::class, [
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $variant->id,
            'warehouse_id' => $this->toWarehouse->id,
            'loss_category' => 'shortfall',
            'lost_base_qty' => 10,
            'damaged_base_qty' => 5,
            'total_financial_loss' => 150.00,
            'notes' => 'Damaged during transit',
        ]);
    });

    it('can record loss for partially_received requisition', function () {
        $requisition = TransferRequisition::factory()->create([
            'status' => TransferRequisitionStatus::PartiallyReceived,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $variant = ProductVariant::factory()->create();
        TransferRequisitionItem::factory()->for($requisition)->create([
            'product_variant_id' => $variant->id,
            'shipped_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => $variant->base_unit_name,
            'approved_unit_ratio' => 1,
        ]);

        InTransit::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'transfer_requisition_item_id' => $requisition->items->first()->id,
            'product_variant_id' => $variant->id,
            'dispatched_base_qty' => 100,
            'status' => InTransitStatus::PartiallyReceived,
        ]);

        livewire(ListTransferRequisitions::class)
            ->loadTable()
            ->callAction(TestAction::make('recordLoss')->table($requisition), [
                'product_variant_id' => $variant->id,
                'loss_category' => 'damage',
                'lost_base_qty' => 0,
                'damaged_base_qty' => 8,
                'total_financial_loss' => 80.00,
                'notes' => 'Water damage in transit',
            ])
            ->assertNotified();

        assertDatabaseHas(LossLedger::class, [
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $variant->id,
            'loss_category' => 'damage',
            'lost_base_qty' => 0,
            'damaged_base_qty' => 8,
            'total_financial_loss' => 80.00,
        ]);
    });

    it('can record loss for completed requisition', function () {
        $requisition = TransferRequisition::factory()->create([
            'status' => TransferRequisitionStatus::Completed,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $variant = ProductVariant::factory()->create();
        TransferRequisitionItem::factory()->for($requisition)->create([
            'product_variant_id' => $variant->id,
            'shipped_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => $variant->base_unit_name,
            'approved_unit_ratio' => 1,
        ]);

        InTransit::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'transfer_requisition_item_id' => $requisition->items->first()->id,
            'product_variant_id' => $variant->id,
            'dispatched_base_qty' => 100,
            'status' => InTransitStatus::Cleared,
        ]);

        livewire(ListTransferRequisitions::class)
            ->loadTable()
            ->callAction(TestAction::make('recordLoss')->table($requisition), [
                'product_variant_id' => $variant->id,
                'loss_category' => 'spoilage',
                'lost_base_qty' => 5,
                'damaged_base_qty' => 0,
                'total_financial_loss' => 75.00,
                'notes' => 'Expired goods found',
            ])
            ->assertNotified();

        assertDatabaseHas(LossLedger::class, [
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $variant->id,
            'loss_category' => 'spoilage',
            'lost_base_qty' => 5,
            'damaged_base_qty' => 0,
            'total_financial_loss' => 75.00,
        ]);
    });

    it('cannot record loss for draft requisition (action not visible)', function () {
        $requisition = TransferRequisition::factory()->create([
            'status' => TransferRequisitionStatus::Draft,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $variant = ProductVariant::factory()->create();
        TransferRequisitionItem::factory()->for($requisition)->create([
            'product_variant_id' => $variant->id,
            'shipped_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => $variant->base_unit_name,
            'approved_unit_ratio' => 1,
        ]);

        livewire(ListTransferRequisitions::class)
            ->loadTable()
            ->assertTableActionHidden('recordLoss', $requisition);
    });

    it('non-admin user cannot record loss without proper authorization', function () {
        $unauthorizedUser = User::factory()->warehouseStaff()->create();
        // User does NOT access requisition's warehouses
        $this->actingAs($unauthorizedUser);

        $requisition = TransferRequisition::factory()->create([
            'status' => TransferRequisitionStatus::Dispatched,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $variant = ProductVariant::factory()->create();
        TransferRequisitionItem::factory()->for($requisition)->create([
            'product_variant_id' => $variant->id,
            'shipped_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => $variant->base_unit_name,
            'approved_unit_ratio' => 1,
        ]);

        InTransit::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'transfer_requisition_item_id' => $requisition->items->first()->id,
            'product_variant_id' => $variant->id,
            'dispatched_base_qty' => 100,
            'status' => InTransitStatus::InTransit,
        ]);

        // Unauthorized user should not see the requisition at all due to getEloquentQuery scope
        livewire(ListTransferRequisitions::class)
            ->loadTable()
            ->assertCountTableRecords(0);
    });

    it('records unit_cost_price snapshot from variant', function () {
        $requisition = TransferRequisition::factory()->create([
            'status' => TransferRequisitionStatus::Dispatched,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'cost_price' => 25.50,
            'sale_price' => 30.00,
            'is_current' => true,
        ]);
        TransferRequisitionItem::factory()->for($requisition)->create([
            'product_variant_id' => $variant->id,
            'shipped_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => $variant->base_unit_name,
            'approved_unit_ratio' => 1,
        ]);

        InTransit::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'transfer_requisition_item_id' => $requisition->items->first()->id,
            'product_variant_id' => $variant->id,
            'dispatched_base_qty' => 100,
            'status' => InTransitStatus::InTransit,
        ]);

        livewire(ListTransferRequisitions::class)
            ->loadTable()
            ->callAction(TestAction::make('recordLoss')->table($requisition), [
                'product_variant_id' => $variant->id,
                'loss_category' => 'theft',
                'lost_base_qty' => 20,
                'damaged_base_qty' => 0,
                'total_financial_loss' => 510.00,
                'notes' => 'Theft during transit',
            ])
            ->assertNotified();

        assertDatabaseHas(LossLedger::class, [
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $variant->id,
            'loss_category' => 'theft',
            'lost_base_qty' => 20,
            'damaged_base_qty' => 0,
            'unit_cost_price' => 25.50,
            'total_financial_loss' => 510.00,
        ]);
    });

    it('records recorded_by and recorded_at automatically', function () {
        $requisition = TransferRequisition::factory()->create([
            'status' => TransferRequisitionStatus::Dispatched,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $variant = ProductVariant::factory()->create();
        TransferRequisitionItem::factory()->for($requisition)->create([
            'product_variant_id' => $variant->id,
            'shipped_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => $variant->base_unit_name,
            'approved_unit_ratio' => 1,
        ]);

        InTransit::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'transfer_requisition_item_id' => $requisition->items->first()->id,
            'product_variant_id' => $variant->id,
            'dispatched_base_qty' => 100,
            'status' => InTransitStatus::InTransit,
        ]);

        $beforeTime = now()->subSecond();

        livewire(ListTransferRequisitions::class)
            ->loadTable()
            ->callAction(TestAction::make('recordLoss')->table($requisition), [
                'product_variant_id' => $variant->id,
                'loss_category' => 'other',
                'lost_base_qty' => 1,
                'damaged_base_qty' => 0,
                'total_financial_loss' => 10.00,
            ])
            ->assertNotified();

        $loss = LossLedger::where('transfer_requisition_id', $requisition->id)->first();
        expect($loss->recorded_by)->toBe($this->admin->id);
        expect($loss->recorded_at)->toBeGreaterThanOrEqual($beforeTime);
    });
});
