<?php

declare(strict_types=1);

use Tests\Datasets\Columns\DirectTransferColumns;
use Tests\Datasets\Columns\InTransitColumns;
use Tests\Datasets\Columns\LossLedgerColumns;
use Tests\Datasets\Columns\ProductVariantColumns;
use Tests\Datasets\Columns\TransferRequisitionColumns;
use Tests\Datasets\Columns\TransferRequisitionItemRevisionColumns;
use Tests\Datasets\Columns\UserColumns;
use Tests\Datasets\Columns\WarehouseColumns;
use Tests\Datasets\Enums\InTransitStatusValues;
use Tests\Datasets\Enums\LossCategoryValues;
use Tests\Datasets\Enums\NegotiationSideValues;
use Tests\Datasets\Enums\RevisionStatusValues;
use Tests\Datasets\Enums\StockMovementTypeValues;
use Tests\Datasets\Enums\TransferRequisitionStatusValues;
use Tests\Datasets\Roles\RoleAccess;
use Tests\Datasets\Roles\RoleActionVisibility;
use Tests\Datasets\Roles\RoleColumnVisibility;
use Tests\Datasets\Roles\RolePolicyExpectations;
use Tests\Datasets\Validation\ProductVariantValidation;
use Tests\Datasets\Validation\TransferRequisitionValidation;
use Tests\Datasets\Validation\WarehouseValidation;

/*
|--------------------------------------------------------------------------
| Shared Datasets
|--------------------------------------------------------------------------
|
| Pest 4 datasets() for shared test data across test files.
| Scoped to the test file where defined, accessible via ->with() or parametrized tests.
|
*/

dataset('product_variant.columns', fn () => [
    'index' => ProductVariantColumns::index(),
    'searchable' => ProductVariantColumns::searchable(),
    'filterable' => ProductVariantColumns::filterable(),
    'sortable' => ProductVariantColumns::sortable(),
    'create' => ProductVariantColumns::create(),
    'edit' => ProductVariantColumns::edit(),
    'view' => ProductVariantColumns::view(),
]);

dataset('direct_transfer.columns', fn () => [
    'index' => DirectTransferColumns::index(),
    'sortable' => DirectTransferColumns::sortable(),
    'filterable' => DirectTransferColumns::filterable(),
    'searchable' => DirectTransferColumns::searchable(),
]);

dataset('in_transit.columns', fn () => [
    'index' => InTransitColumns::index(),
    'sortable' => InTransitColumns::sortable(),
    'filterable' => InTransitColumns::filterable(),
    'searchable' => InTransitColumns::searchable(),
    'view' => InTransitColumns::view(),
]);

dataset('loss_ledger.columns', fn () => [
    'index' => LossLedgerColumns::index(),
    'sortable' => LossLedgerColumns::sortable(),
    'filterable' => LossLedgerColumns::filterable(),
    'searchable' => LossLedgerColumns::searchable(),
    'view' => LossLedgerColumns::view(),
]);

dataset('transfer_requisition.columns', fn () => [
    'index' => TransferRequisitionColumns::index(),
    'sortable' => TransferRequisitionColumns::sortable(),
    'filterable' => TransferRequisitionColumns::filterable(),
    'searchable' => TransferRequisitionColumns::searchable(),
    'view' => TransferRequisitionColumns::view(),
]);

dataset('warehouse.columns', fn () => [
    'index' => WarehouseColumns::index(),
    'sortable' => WarehouseColumns::sortable(),
    'filterable' => WarehouseColumns::filterable(),
    'searchable' => WarehouseColumns::searchable(),
    'create' => WarehouseColumns::create(),
    'edit' => WarehouseColumns::edit(),
    'view' => WarehouseColumns::view(),
]);

dataset('user.columns', fn () => [
    'index' => UserColumns::index(),
    'sortable' => UserColumns::sortable(),
    'filterable' => UserColumns::filterable(),
    'searchable' => UserColumns::searchable(),
    'create' => UserColumns::create(),
    'edit' => UserColumns::edit(),
    'view' => UserColumns::view(),
]);

dataset('transfer_requisition_item_revision.columns', fn () => [
    'index' => TransferRequisitionItemRevisionColumns::index(),
    'sortable' => TransferRequisitionItemRevisionColumns::sortable(),
    'filterable' => TransferRequisitionItemRevisionColumns::filterable(),
    'searchable' => TransferRequisitionItemRevisionColumns::searchable(),
    'view' => TransferRequisitionItemRevisionColumns::view(),
]);

dataset('stock_movement_type.values', fn () => [
    'all' => StockMovementTypeValues::all(),
    'inbound' => StockMovementTypeValues::inbound(),
    'outbound' => StockMovementTypeValues::outbound(),
    'adjustment' => StockMovementTypeValues::adjustment(),
    'transfer' => StockMovementTypeValues::transfer(),
    'in_transit' => StockMovementTypeValues::inTransit(),
]);

dataset('transfer_requisition_status.values', fn () => [
    'all' => TransferRequisitionStatusValues::all(),
    'pre_dispatch' => TransferRequisitionStatusValues::preDispatch(),
    'receivable' => TransferRequisitionStatusValues::receivable(),
    'terminal' => TransferRequisitionStatusValues::terminal(),
    'negotiable' => TransferRequisitionStatusValues::negotiable(),
]);

dataset('in_transit_status.values', fn () => [
    'all' => InTransitStatusValues::all(),
    'active' => InTransitStatusValues::active(),
    'completed' => InTransitStatusValues::completed(),
]);

dataset('loss_category.values', fn () => [
    'all' => LossCategoryValues::all(),
    'physical_loss' => LossCategoryValues::physicalLoss(),
    'damage_related' => LossCategoryValues::damageRelated(),
]);

dataset('negotiation_side.values', fn () => [
    'all' => NegotiationSideValues::all(),
    'requestor' => NegotiationSideValues::requestor(),
    'fulfiller' => NegotiationSideValues::fulfiller(),
]);

dataset('revision_status.values', fn () => [
    'all' => RevisionStatusValues::all(),
    'pending' => RevisionStatusValues::pending(),
    'resolved' => RevisionStatusValues::resolved(),
]);

dataset('role.access', fn () => [
    'all' => RoleAccess::all(),
    'with_warehouse_access' => RoleAccess::withWarehouseAccess(),
    'admin_only' => RoleAccess::adminOnly(),
    'non_admin' => RoleAccess::nonAdmin(),
    'managers' => RoleAccess::managers(),
]);

dataset('role.action_visibility', fn () => [
    'index_actions' => RoleActionVisibility::indexActions(),
    'view_actions' => RoleActionVisibility::viewActions(),
    'bulk_actions' => RoleActionVisibility::bulkActions(),
]);

dataset('role.column_visibility', fn () => [
    'expected_visible' => RoleColumnVisibility::expectedVisible(),
    'expected_hidden' => RoleColumnVisibility::expectedHidden(),
    'warehouse_scoped' => RoleColumnVisibility::warehouseScoped(),
]);

dataset('role.policy_expectations', fn () => [
    'policy_results' => RolePolicyExpectations::policyResults(),
]);

dataset('product_variant.validation', fn () => [
    'create' => ProductVariantValidation::create(),
    'update' => ProductVariantValidation::update(),
]);

dataset('transfer_requisition.validation', fn () => [
    'create' => TransferRequisitionValidation::create(),
    'update' => TransferRequisitionValidation::update(),
]);

dataset('warehouse.validation', fn () => [
    'create' => WarehouseValidation::create(),
    'update' => WarehouseValidation::update(),
]);
