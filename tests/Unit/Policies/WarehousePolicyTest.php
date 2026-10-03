<?php

declare(strict_types=1);

use App\Models\DirectTransfer;
use App\Models\LossLedger;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\WarehousePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new WarehousePolicy());

it('viewAny grants admin/auditor and staff with assignments', function () {
    expect($this->policy->viewAny(User::factory()->admin()->create()))->toBeTrue();
    expect($this->policy->viewAny(User::factory()->auditor()->create()))->toBeTrue();
    expect($this->policy->viewAny(User::factory()->create()))->toBeFalse();

    $staff = User::factory()->create();
    $staff->warehouses()->attach(Warehouse::factory()->create()->id);
    expect($this->policy->viewAny($staff))->toBeTrue();
});

it('view grants admin/auditor/assigned-staff', function () {
    $warehouse = Warehouse::factory()->create();
    expect($this->policy->view(User::factory()->admin()->create(), $warehouse))->toBeTrue();
    expect($this->policy->view(User::factory()->auditor()->create(), $warehouse))->toBeTrue();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($warehouse->id);
    expect($this->policy->view($staff, $warehouse))->toBeTrue();
    expect($this->policy->view(User::factory()->create(), $warehouse))->toBeFalse();
});

it('create and update are admin-only', function () {
    $warehouse = Warehouse::factory()->create();
    $admin = User::factory()->admin()->create();
    expect($this->policy->create($admin))->toBeTrue();
    expect($this->policy->update($admin, $warehouse))->toBeTrue();

    expect($this->policy->create(User::factory()->create()))->toBeFalse();
    expect($this->policy->create(User::factory()->branchManager()->create()))->toBeFalse();
});

it('delete is denied for non-admins', function () {
    $warehouse = Warehouse::factory()->create();
    expect($this->policy->delete(User::factory()->create(), $warehouse))->toBeFalse();
    expect($this->policy->delete(User::factory()->branchManager()->create(), $warehouse))->toBeFalse();
});

it('delete is allowed when no references exist', function () {
    $warehouse = Warehouse::factory()->create();
    expect($this->policy->delete(User::factory()->admin()->create(), $warehouse))->toBeTrue();
});

it('delete is blocked by stock movements', function () {
    $warehouse = Warehouse::factory()->create();
    StockMovement::factory()->create(['warehouse_id' => $warehouse->id]);
    expect($this->policy->delete(User::factory()->admin()->create(), $warehouse))->toBeFalse();
});

it('delete is blocked by purchase orders', function () {
    $warehouse = Warehouse::factory()->create();
    PurchaseOrder::factory()->create(['warehouse_id' => $warehouse->id]);
    expect($this->policy->delete(User::factory()->admin()->create(), $warehouse))->toBeFalse();
});

it('delete is blocked by sales orders', function () {
    $warehouse = Warehouse::factory()->create();
    SalesOrder::factory()->create(['warehouse_id' => $warehouse->id]);
    expect($this->policy->delete(User::factory()->admin()->create(), $warehouse))->toBeFalse();
});

it('delete is blocked by transfer requisitions (from or to)', function () {
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    $from = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $other->id,
    ]);
    expect($this->policy->delete(User::factory()->admin()->create(), $warehouse))->toBeFalse();

    $from->forceDelete();

    TransferRequisition::factory()->create([
        'from_warehouse_id' => $other->id,
        'to_warehouse_id' => $warehouse->id,
    ]);
    expect($this->policy->delete(User::factory()->admin()->create(), $warehouse))->toBeFalse();
});

it('delete is blocked by direct transfers (from or to)', function () {
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    $dt = DirectTransfer::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $other->id,
    ]);
    expect($this->policy->delete(User::factory()->admin()->create(), $warehouse))->toBeFalse();

    $dt->forceDelete();

    DirectTransfer::factory()->create([
        'from_warehouse_id' => $other->id,
        'to_warehouse_id' => $warehouse->id,
    ]);
    expect($this->policy->delete(User::factory()->admin()->create(), $warehouse))->toBeFalse();
});

it('delete is blocked by loss ledgers', function () {
    $warehouse = Warehouse::factory()->create();
    LossLedger::factory()->create(['warehouse_id' => $warehouse->id]);
    expect($this->policy->delete(User::factory()->admin()->create(), $warehouse))->toBeFalse();
});

it('deleteAny is admin-only', function () {
    expect($this->policy->deleteAny(User::factory()->admin()->create()))->toBeTrue();
    expect($this->policy->deleteAny(User::factory()->create()))->toBeFalse();
    expect($this->policy->deleteAny(User::factory()->branchManager()->create()))->toBeFalse();
});

it('grants read-only access to branch manager with assignment', function () {
    $warehouse = Warehouse::factory()->create();
    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach($warehouse->id);

    expect($this->policy->viewAny($bm))->toBeTrue();
    expect($this->policy->view($bm, $warehouse))->toBeTrue();
    expect($this->policy->create($bm))->toBeFalse();
    expect($this->policy->update($bm, $warehouse))->toBeFalse();
    expect($this->policy->delete($bm, $warehouse))->toBeFalse();
});
