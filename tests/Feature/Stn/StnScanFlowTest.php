<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Livewire\Stn\ScanForm;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * §21 / §6.2 — STN print + signed-QR scan intake, end to end.
 *
 * Architecture suites prove the route exists and the views resolve;
 * this suite proves the workflow behaves. Every mutation the browser
 * can trigger (the `stn.scan` GET, the Livewire `submit`) is exercised
 * against the real service boundary — nothing is mocked.
 */
uses(RefreshDatabase::class);

/**
 * A dispatched requisition with one item, its quantities already
 * shipped out of the source warehouse (so intake is meaningful).
 *
 * @return array{requisition: TransferRequisition, item: TransferRequisitionItem, to: Warehouse}
 */
function stnDispatchedRequisition(int $approvedQty = 20): array
{
    [$from, $to] = Warehouse::factory()->count(2)->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $from->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => $approvedQty * 10,
    ]);

    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);

    $item = TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'approved_unit_name' => 'pc',
        'approved_unit_ratio' => 1,
        'approved_qty' => $approvedQty,
        'approved_base_qty' => $approvedQty,
        'shipped_base_qty' => 0,
    ]);

    app(App\Services\InventoryService::class)->dispatchTransfer($requisition);

    return [
        'requisition' => $requisition->fresh(),
        'item' => $item->fresh(),
        'to' => $to,
    ];
}

// (a) A destination-warehouse user with a valid signature reaches the scan page.
it('renders the scan page for an authorized user via a signed URL', function () {
    $fx = stnDispatchedRequisition();

    $user = User::factory()->create();
    $user->warehouses()->attach($fx['to']->id);

    $url = URL::temporarySignedRoute('stn.scan', now()->addDays(7), [
        'transferRequisition' => $fx['requisition']->id,
    ]);

    $this->actingAs($user)->get($url)->assertOk();
});

// (b) The signature is the access control — an unsigned URL is rejected.
it('rejects an unsigned scan URL', function () {
    $fx = stnDispatchedRequisition();

    $user = User::factory()->create();
    $user->warehouses()->attach($fx['to']->id);

    $this->actingAs($user)
        ->get(route('stn.scan', ['transferRequisition' => $fx['requisition']->id]))
        ->assertForbidden();
});

// (c) A signed URL is not a capability: the `receive` policy still applies.
it('rejects a user not assigned to the destination warehouse', function () {
    $fx = stnDispatchedRequisition();

    $outsider = User::factory()->create();
    $outsider->warehouses()->attach(Warehouse::factory()->create()->id);

    $url = URL::temporarySignedRoute('stn.scan', now()->addDays(7), [
        'transferRequisition' => $fx['requisition']->id,
    ]);

    $this->actingAs($outsider)->get($url)->assertForbidden();
});

// (d) Intake before dispatch is meaningless — the controller aborts 403.
it('rejects scan for a requisition that has not been dispatched', function () {
    [$from, $to] = Warehouse::factory()->count(2)->create();
    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);

    $user = User::factory()->create();
    $user->warehouses()->attach($to->id);

    $url = URL::temporarySignedRoute('stn.scan', now()->addDays(7), [
        'transferRequisition' => $requisition->id,
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();
});

// (e) The printable note renders (and its QR step runs) for a viewer.
it('renders the printable STN for an authorized user', function () {
    $fx = stnDispatchedRequisition();

    $user = User::factory()->create();
    $user->warehouses()->attach($fx['to']->id);

    $this->actingAs($user)
        ->get(route('stn.print', ['transferRequisition' => $fx['requisition']->id]))
        ->assertOk()
        ->assertSee($fx['requisition']->reference_code);
});

// (f) The happy path writes exactly one receipt movement and closes intake.
it('receives a full payload through the scan form', function () {
    $fx = stnDispatchedRequisition(approvedQty: 20);

    $receiver = User::factory()->create();
    $receiver->warehouses()->attach($fx['to']->id);
    $this->actingAs($receiver);

    Livewire::test(ScanForm::class, ['requisition' => $fx['requisition']->id])
        ->set("lines.{$fx['item']->id}.received_good", 20)
        ->set("lines.{$fx['item']->id}.received_damaged", 0)
        ->call('submit')
        ->assertSet('error', null);

    expect($fx['item']->fresh()->received_good_base_qty)->toBe(20);
    expect($fx['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::Completed);
    expect(StockMovement::where('type', StockMovementType::TransferIn->value)->count())->toBe(1);
});

// (g) Over-receive is a domain error surfaced in the form, not a ledger write.
it('surfaces an over-receive as a form error and writes no movement', function () {
    $fx = stnDispatchedRequisition(approvedQty: 20);

    $receiver = User::factory()->create();
    $receiver->warehouses()->attach($fx['to']->id);
    $this->actingAs($receiver);

    $component = Livewire::test(ScanForm::class, ['requisition' => $fx['requisition']->id])
        ->set("lines.{$fx['item']->id}.received_good", 999)
        ->call('submit');

    expect($component->get('error'))->not->toBeNull();
    expect($fx['item']->fresh()->received_good_base_qty)->toBe(0);
    expect(StockMovement::where('type', StockMovementType::TransferIn->value)->count())->toBe(0);
});
