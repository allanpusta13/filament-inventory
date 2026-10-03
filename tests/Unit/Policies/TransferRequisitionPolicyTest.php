<?php

declare(strict_types=1);

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\TransferRequisitionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->policy = new TransferRequisitionPolicy());

function trPolicyRequisition(TransferRequisitionStatus $status = TransferRequisitionStatus::Draft): array
{
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $req = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => $status,
    ]);

    return ['req' => $req, 'from' => $from, 'to' => $to];
}

it('viewAny is universal', function () {
    foreach ([User::factory()->admin()->create(), User::factory()->auditor()->create(),
        User::factory()->create(), User::factory()->branchManager()->create()] as $user) {
        expect($this->policy->viewAny($user))->toBeTrue();
    }
});

it('view grants admin/auditor/assigned-staff', function () {
    ['req' => $req, 'from' => $from] = trPolicyRequisition();

    expect($this->policy->view(User::factory()->admin()->create(), $req))->toBeTrue();
    expect($this->policy->view(User::factory()->auditor()->create(), $req))->toBeTrue();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($from->id);
    expect($this->policy->view($staff, $req))->toBeTrue();

    $stranger = User::factory()->create();
    expect($this->policy->view($stranger, $req))->toBeFalse();
});

it('create requires admin or non-auditor with two assignments', function () {
    expect($this->policy->create(User::factory()->admin()->create()))->toBeTrue();

    $two = User::factory()->create();
    $two->warehouses()->attach(Warehouse::factory()->count(2)->create()->pluck('id'));
    expect($this->policy->create($two))->toBeTrue();

    $one = User::factory()->create();
    $one->warehouses()->attach(Warehouse::factory()->create()->id);
    expect($this->policy->create($one))->toBeFalse();

    $auditor = User::factory()->auditor()->create();
    $auditor->warehouses()->attach(Warehouse::factory()->count(2)->create()->pluck('id'));
    expect($this->policy->create($auditor))->toBeFalse();
});

it('create grants branch manager when two assignments', function () {
    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach(Warehouse::factory()->count(2)->create()->pluck('id'));
    expect($this->policy->create($bm))->toBeTrue();
});

it('update is draft-only on the source warehouse', function () {
    ['req' => $draft, 'from' => $from] = trPolicyRequisition(TransferRequisitionStatus::Draft);

    $staff = User::factory()->create();
    $staff->warehouses()->attach($from->id);
    expect($this->policy->update($staff, $draft))->toBeTrue();
    expect($this->policy->update($staff, $draft->fresh()->setAttribute('status', TransferRequisitionStatus::Confirmed)))->toBeFalse();
});

it('delete is admin + draft/cancelled only', function () {
    $admin = User::factory()->admin()->create();
    ['req' => $draft] = trPolicyRequisition(TransferRequisitionStatus::Draft);
    ['req' => $dispatched] = trPolicyRequisition(TransferRequisitionStatus::Dispatched);

    expect($this->policy->delete($admin, $draft))->toBeTrue();
    expect($this->policy->delete($admin, $dispatched))->toBeFalse();
    expect($this->policy->delete(User::factory()->create(), $draft))->toBeFalse();
});

it('admin-only abilities are admin-only', function () {
    ['req' => $req] = trPolicyRequisition();
    foreach (['deleteAny', 'restoreAny', 'forceDeleteAny'] as $ability) {
        expect($this->policy->{$ability}(User::factory()->admin()->create()))->toBeTrue();
        expect($this->policy->{$ability}(User::factory()->create()))->toBeFalse();
        expect($this->policy->{$ability}(User::factory()->branchManager()->create()))->toBeFalse();
    }
});

it('submitRequest requires draft + source warehouse + non-auditor', function () {
    ['req' => $draft, 'from' => $from] = trPolicyRequisition(TransferRequisitionStatus::Draft);

    $staff = User::factory()->create();
    $staff->warehouses()->attach($from->id);
    expect($this->policy->submitRequest($staff, $draft))->toBeTrue();

    $auditor = User::factory()->auditor()->create();
    $auditor->warehouses()->attach($from->id);
    expect($this->policy->submitRequest($auditor, $draft))->toBeFalse();
});

it('confirm requires destination warehouse', function () {
    ['req' => $req, 'to' => $to] = trPolicyRequisition(TransferRequisitionStatus::Requested);

    $staff = User::factory()->create();
    $staff->warehouses()->attach($to->id);
    expect($this->policy->confirm($staff, $req))->toBeTrue();

    expect($this->policy->confirm(User::factory()->auditor()->create(), $req))->toBeFalse();
});

it('dispatch requires source warehouse', function () {
    ['req' => $req, 'from' => $from] = trPolicyRequisition(TransferRequisitionStatus::Confirmed);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($from->id);
    expect($this->policy->dispatch($staff, $req))->toBeTrue();
});

it('receive requires destination warehouse', function () {
    ['req' => $req, 'to' => $to] = trPolicyRequisition(TransferRequisitionStatus::Dispatched);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($to->id);
    expect($this->policy->receive($staff, $req))->toBeTrue();
});

it('recordLoss requires destination warehouse', function () {
    ['req' => $req, 'to' => $to] = trPolicyRequisition(TransferRequisitionStatus::Dispatched);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($to->id);
    expect($this->policy->recordLoss($staff, $req))->toBeTrue();
});

it('cancel honors canBeCancelled boundary', function () {
    ['req' => $cancelable, 'from' => $from] = trPolicyRequisition(TransferRequisitionStatus::Confirmed);
    ['req' => $terminal] = trPolicyRequisition(TransferRequisitionStatus::Completed);

    $staff = User::factory()->create();
    $staff->warehouses()->attach($from->id);

    expect($this->policy->cancel($staff, $cancelable))->toBeTrue();
    expect($this->policy->cancel($staff, $terminal))->toBeFalse();
});

it('negotiate requires a review state and either endpoint', function () {
    ['req' => $req, 'from' => $from, 'to' => $to] = trPolicyRequisition(TransferRequisitionStatus::UnderReviewFulfiller);

    $fromStaff = User::factory()->create();
    $fromStaff->warehouses()->attach($from->id);
    expect($this->policy->negotiate($fromStaff, $req))->toBeTrue();

    $toStaff = User::factory()->create();
    $toStaff->warehouses()->attach($to->id);
    expect($this->policy->negotiate($toStaff, $req))->toBeTrue();

    expect($this->policy->negotiate(User::factory()->auditor()->create(), $req))->toBeFalse();
});

it('acceptRevision / rejectRevision follow the same shape as negotiate', function () {
    ['req' => $req, 'from' => $from] = trPolicyRequisition(TransferRequisitionStatus::UnderReviewRequestor);
    $staff = User::factory()->create();
    $staff->warehouses()->attach($from->id);

    expect($this->policy->acceptRevision($staff, $req))->toBeTrue();
    expect($this->policy->rejectRevision($staff, $req))->toBeTrue();
    expect($this->policy->acceptRevision(User::factory()->auditor()->create(), $req))->toBeFalse();
});

it('viewAuditFilters is admin or auditor only', function () {
    expect($this->policy->viewAuditFilters(User::factory()->admin()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->auditor()->create(), 'X'))->toBeTrue();
    expect($this->policy->viewAuditFilters(User::factory()->create(), 'X'))->toBeFalse();
    expect($this->policy->viewAuditFilters(User::factory()->branchManager()->create(), 'X'))->toBeFalse();
});

it('grants operational abilities to branch manager with warehouse scope', function () {
    ['req' => $req, 'from' => $from, 'to' => $to] = trPolicyRequisition(TransferRequisitionStatus::Confirmed);
    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach([$from->id, $to->id]);

    expect($this->policy->view($bm, $req))->toBeTrue();
    expect($this->policy->dispatch($bm, $req))->toBeTrue();
    expect($this->policy->confirm($bm, $req))->toBeTrue();
});
