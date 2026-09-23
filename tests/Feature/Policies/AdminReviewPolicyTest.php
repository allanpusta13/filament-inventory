<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\LossLedgerPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\TransferRequisitionPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->auditor = User::factory()->auditor()->create();
    $this->branchManager = User::factory()->branchManager()->create();
    $this->warehouseStaff = User::factory()->warehouseStaff()->create();
});

describe('viewAdminReview - admin/auditor-only review-surface gate', function () {
    it('TransferRequisitionPolicy allows admin and auditor only', function () {
        $policy = new TransferRequisitionPolicy();

        expect($policy->viewAdminReview($this->admin))->toBeTrue()
            ->and($policy->viewAdminReview($this->auditor))->toBeTrue()
            ->and($policy->viewAdminReview($this->branchManager))->toBeFalse()
            ->and($policy->viewAdminReview($this->warehouseStaff))->toBeFalse();
    });

    it('StockMovementPolicy allows admin and auditor only', function () {
        $policy = new StockMovementPolicy();

        expect($policy->viewAdminReview($this->admin))->toBeTrue()
            ->and($policy->viewAdminReview($this->auditor))->toBeTrue()
            ->and($policy->viewAdminReview($this->branchManager))->toBeFalse()
            ->and($policy->viewAdminReview($this->warehouseStaff))->toBeFalse();
    });

    it('LossLedgerPolicy allows admin and auditor only', function () {
        $policy = new LossLedgerPolicy();

        expect($policy->viewAdminReview($this->admin))->toBeTrue()
            ->and($policy->viewAdminReview($this->auditor))->toBeTrue()
            ->and($policy->viewAdminReview($this->branchManager))->toBeFalse()
            ->and($policy->viewAdminReview($this->warehouseStaff))->toBeFalse();
    });

    it('PurchaseOrderPolicy allows admin and auditor only', function () {
        $policy = new PurchaseOrderPolicy();

        expect($policy->viewAdminReview($this->admin))->toBeTrue()
            ->and($policy->viewAdminReview($this->auditor))->toBeTrue()
            ->and($policy->viewAdminReview($this->branchManager))->toBeFalse()
            ->and($policy->viewAdminReview($this->warehouseStaff))->toBeFalse();
    });

    it('SalesOrderPolicy allows admin and auditor only', function () {
        $policy = new SalesOrderPolicy();

        expect($policy->viewAdminReview($this->admin))->toBeTrue()
            ->and($policy->viewAdminReview($this->auditor))->toBeTrue()
            ->and($policy->viewAdminReview($this->branchManager))->toBeFalse()
            ->and($policy->viewAdminReview($this->warehouseStaff))->toBeFalse();
    });

    it('is reachable through Gate via class-string delegation', function () {
        expect($this->admin->can('viewAdminReview', App\Models\TransferRequisition::class))->toBeTrue()
            ->and($this->warehouseStaff->can('viewAdminReview', App\Models\TransferRequisition::class))->toBeFalse()
            ->and($this->auditor->can('viewAdminReview', App\Models\StockMovement::class))->toBeTrue()
            ->and($this->branchManager->can('viewAdminReview', App\Models\StockMovement::class))->toBeFalse();
    });
});
