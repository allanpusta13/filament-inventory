<?php

declare(strict_types=1);

use App\Filament\Resources\LossLedgers\Pages\ViewLossLedger;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    LossLedger::truncate();
    TransferRequisitionItem::truncate();
    TransferRequisition::truncate();
    ProductVariant::truncate();
    Warehouse::truncate();
    User::truncate();

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('LossLedgerResource edge cases', function () {
    it('validates loss_category enum', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['loss_category' => 'shortfall']);
        expect($loss->loss_category)->toBe('shortfall');

        $loss2 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['loss_category' => 'damage']);
        expect($loss2->loss_category)->toBe('damage');

        $loss3 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['loss_category' => 'spoilage']);
        expect($loss3->loss_category)->toBe('spoilage');

        $loss4 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['loss_category' => 'theft']);
        expect($loss4->loss_category)->toBe('theft');

        $loss5 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['loss_category' => 'other']);
        expect($loss5->loss_category)->toBe('other');
    });

    it('validates lost_base_qty is integer', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['lost_base_qty' => 100]);
        expect($loss->lost_base_qty)->toBe(100);
        expect($loss->lost_base_qty)->toBeInt();
    });

    it('validates damaged_base_qty is integer', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['damaged_base_qty' => 50]);
        expect($loss->damaged_base_qty)->toBe(50);
        expect($loss->damaged_base_qty)->toBeInt();
    });

    it('allows zero damaged_base_qty', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['damaged_base_qty' => 0]);
        expect($loss->damaged_base_qty)->toBe(0);
    });

    it('can search by reference_code', function () {
        LossLedger::truncate();
        $req1 = TransferRequisition::factory()->create(['reference_code' => 'DTR-001']);
        $req2 = TransferRequisition::factory()->create(['reference_code' => 'DTR-002']);
        $item1 = TransferRequisitionItem::factory()->for($req1)->create();
        $item2 = TransferRequisitionItem::factory()->for($req2)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $loss1 = LossLedger::factory()->for($req1, 'transferRequisition')->for($item1, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create();
        LossLedger::factory()->for($req2, 'transferRequisition')->for($item2, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create();

        $results = LossLedger::whereHas('transferRequisition', fn ($q) => $q->where('reference_code', 'like', '%DTR-001%'))->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($loss1->id);
    });

    it('can filter by loss_category', function () {
        LossLedger::truncate();
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $shortfall = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['loss_category' => 'shortfall']);
        LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['loss_category' => 'damage']);

        $results = LossLedger::where('loss_category', 'shortfall')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($shortfall->id);
    });

    it('can filter by warehouse', function () {
        LossLedger::truncate();
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();

        $loss1 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse1, 'warehouse')->create();
        LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse2, 'warehouse')->create();

        $results = LossLedger::where('warehouse_id', $warehouse1->id)->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($loss1->id);
    });

    it('can sort by lost_base_qty', function () {
        LossLedger::truncate();
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $l1 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['lost_base_qty' => 300]);
        $l2 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['lost_base_qty' => 100]);
        $l3 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['lost_base_qty' => 200]);

        $results = LossLedger::orderBy('lost_base_qty', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$l2->id, $l3->id, $l1->id]);
    });

    it('can sort by total_financial_loss', function () {
        LossLedger::truncate();
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $l1 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['total_financial_loss' => '300.0000']);
        $l2 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['total_financial_loss' => '100.0000']);
        $l3 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['total_financial_loss' => '200.0000']);

        $results = LossLedger::orderBy('total_financial_loss', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$l2->id, $l3->id, $l1->id]);
    });

    it('can sort by recorded_at', function () {
        LossLedger::truncate();
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $l1 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['recorded_at' => now()->subDays(2)]);
        $l2 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['recorded_at' => now()->subDay()]);
        $l3 = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')->create(['recorded_at' => now()]);

        $results = LossLedger::orderBy('recorded_at', 'asc')->get();
        expect($results->pluck('id')->toArray())->toBe([$l1->id, $l2->id, $l3->id]);
    });

    it('renders view page with correct data', function () {
        $requisition = TransferRequisition::factory()->create(['reference_code' => 'DTR-VIEW']);
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create(['sku' => 'SKU-VIEW', 'name' => 'View Variant']);
        $warehouse = Warehouse::factory()->create(['name' => 'Test Warehouse']);
        $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['lost_base_qty' => 50, 'damaged_base_qty' => 10, 'loss_category' => 'shortfall', 'total_financial_loss' => '500.0000']);

        livewire(ViewLossLedger::class, ['record' => $loss->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'transferRequisition.reference_code' => 'DTR-VIEW',
                'productVariant.sku' => 'SKU-VIEW',
                'productVariant.name' => 'View Variant',
                'warehouse.name' => 'Test Warehouse',
                'loss_category' => 'shortfall',
                'lost_base_qty' => '50',
                'damaged_base_qty' => '10',
                'total_financial_loss' => '500.0000',
            ]);
    });

    it('shows unit cost price snapshot', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['unit_cost_price' => '25.5000']);

        expect($loss->unit_cost_price)->toBe('25.5000');
    });

    it('handles loss with requisition item relationship', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create(['id' => 999]);
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['transfer_requisition_item_id' => 999]);

        expect($loss->item->id)->toBe(999);
    });

    it('handles loss with recorded_by relationship', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $user = User::factory()->create(['name' => 'Test User']);

        $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
            ->create(['recorded_by' => $user->id]);

        expect($loss->recordedBy->name)->toBe('Test User');
    });

    it('handles all loss categories in table badge', function () {
        $requisition = TransferRequisition::factory()->create();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $categories = ['shortfall', 'damage', 'spoilage', 'theft', 'other'];
        foreach ($categories as $cat) {
            $loss = LossLedger::factory()->for($requisition, 'transferRequisition')->for($item, 'item')->for($variant, 'productVariant')->for($warehouse, 'warehouse')
                ->create(['loss_category' => $cat]);
            expect($loss->loss_category)->toBe($cat);
        }
    });
});