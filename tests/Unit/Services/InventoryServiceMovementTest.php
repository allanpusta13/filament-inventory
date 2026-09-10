<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

beforeEach(function () {
    $this->service = new InventoryService();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('recordMovement', function () {
    it('creates a movement and stamps the authenticated user as creator', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $movement = $this->service->recordMovement(
            productVariantId: $variant->id,
            warehouseId: $warehouse->id,
            type: StockMovementType::Receive,
            baseQuantity: 100,
        );

        expect($movement->quantity)->toBe(100)
            ->and($movement->type)->toBe(StockMovementType::Receive)
            ->and($movement->created_by)->toBe($this->user->id)
            ->and($movement->unit_name_used)->toBe($variant->base_unit_name);
    });

    it('rejects a deduction that would drive stock negative', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->service->recordMovement($variant->id, $warehouse->id, StockMovementType::Receive, 10);

        expect(fn () => $this->service->recordMovement($variant->id, $warehouse->id, StockMovementType::Ship, -20))
            ->toThrow(Exception::class);

        expect($variant->onHandQuantity($warehouse->id))->toBe(10);
    });

    it('allows a deduction that exactly zeroes out stock', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->service->recordMovement($variant->id, $warehouse->id, StockMovementType::Receive, 50);
        $this->service->recordMovement($variant->id, $warehouse->id, StockMovementType::Ship, -50);

        expect($variant->onHandQuantity($warehouse->id))->toBe(0);
    });

    it('uses the given unit name and ratio over the variant default', function () {
        $variant = ProductVariant::factory()->create(['base_unit_name' => 'piece']);
        $warehouse = Warehouse::factory()->create();

        $movement = $this->service->recordMovement(
            $variant->id, $warehouse->id, StockMovementType::Receive, 240,
            unitName: 'Box', unitRatio: 24,
        );

        expect($movement->unit_name_used)->toBe('Box')
            ->and($movement->unit_ratio_used)->toBe(24);
    });

    it('stores optional reference fields and leaves them null when omitted', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        // Without optional params — all nullable columns remain null
        $bare = $this->service->recordMovement(
            $variant->id, $warehouse->id, StockMovementType::Receive, 10,
        );

        expect($bare->reference_type)->toBeNull()
            ->and($bare->reference_id)->toBeNull()
            ->and($bare->reference_code)->toBeNull()
            ->and($bare->related_movement_id)->toBeNull();

        // With optional params
        $linked = $this->service->recordMovement(
            $variant->id, $warehouse->id, StockMovementType::Receive, 20,
            referenceType: TransferRequisition::class,
            referenceId: '42',
            referenceCode: 'TRQ-00042',
            relatedMovementId: $bare->id,
        );

        expect($linked->reference_type)->toBe(TransferRequisition::class)
            ->and($linked->reference_id)->toBe('42')
            ->and($linked->reference_code)->toBe('TRQ-00042')
            ->and($linked->related_movement_id)->toBe($bare->id);
    });

    it('defaults unit_name_used to the variant base_unit_name when unitName is null', function () {
        $variant = ProductVariant::factory()->create(['base_unit_name' => 'Kilogram']);
        $warehouse = Warehouse::factory()->create();

        $movement = $this->service->recordMovement(
            $variant->id, $warehouse->id, StockMovementType::Receive, 5,
        );

        expect($movement->unit_name_used)->toBe('Kilogram')
            ->and($movement->unit_ratio_used)->toBe(1);
    });
});

describe('directTransfer', function () {
    it('moves stock atomically between two warehouses', function () {
        $variant = ProductVariant::factory()->create();
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();

        $this->service->recordMovement($variant->id, $origin->id, StockMovementType::Receive, 100);

        [$out, $in] = $this->service->directTransfer(
            productVariantId: $variant->id,
            fromWarehouseId: $origin->id,
            toWarehouseId: $destination->id,
            baseQuantity: 40,
        );

        expect($variant->onHandQuantity($origin->id))->toBe(60)
            ->and($variant->onHandQuantity($destination->id))->toBe(40)
            ->and($out->type)->toBe(StockMovementType::TransferOut)
            ->and($out->quantity)->toBe(-40)
            ->and($in->type)->toBe(StockMovementType::TransferIn)
            ->and($in->quantity)->toBe(40);
    });

    it('links the two legs via related_movement_id in both directions', function () {
        $variant = ProductVariant::factory()->create();
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();
        $this->service->recordMovement($variant->id, $origin->id, StockMovementType::Receive, 100);

        [$out, $in] = $this->service->directTransfer($variant->id, $origin->id, $destination->id, 10);

        expect($out->fresh()->related_movement_id)->toBe($in->id)
            ->and($in->fresh()->related_movement_id)->toBe($out->id);
    });

    it('rejects a transfer with insufficient stock and writes no movements', function () {
        $variant = ProductVariant::factory()->create();
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();
        $this->service->recordMovement($variant->id, $origin->id, StockMovementType::Receive, 5);

        expect(fn () => $this->service->directTransfer($variant->id, $origin->id, $destination->id, 10))
            ->toThrow(Exception::class);

        expect(StockMovement::where('product_variant_id', $variant->id)
            ->whereIn('type', [StockMovementType::TransferOut, StockMovementType::TransferIn])
            ->count())->toBe(0);
    });

    it('rejects a transfer to the same warehouse', function () {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $this->service->recordMovement($variant->id, $warehouse->id, StockMovementType::Receive, 100);

        expect(fn () => $this->service->directTransfer($variant->id, $warehouse->id, $warehouse->id, 10))
            ->toThrow(Exception::class);
    });

    it('rejects a zero or negative quantity', function () {
        $variant = ProductVariant::factory()->create();
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();

        expect(fn () => $this->service->directTransfer($variant->id, $origin->id, $destination->id, 0))
            ->toThrow(Exception::class);
    });

    it('uses the given unit name and ratio on both legs', function () {
        $variant = ProductVariant::factory()->create(['base_unit_name' => 'piece']);
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();
        $this->service->recordMovement($variant->id, $origin->id, StockMovementType::Receive, 100);

        [$out, $in] = $this->service->directTransfer(
            $variant->id, $origin->id, $destination->id, 48,
            unitName: 'Box', unitRatio: 24,
        );

        expect($out->unit_name_used)->toBe('Box')
            ->and($out->unit_ratio_used)->toBe(24)
            ->and($in->unit_name_used)->toBe('Box')
            ->and($in->unit_ratio_used)->toBe(24);
    });
});
