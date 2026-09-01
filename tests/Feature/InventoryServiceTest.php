<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

beforeEach(function (): void {
    $this->service = new InventoryService();
    $this->product = Product::factory()->create();
    $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
    $this->warehouse = Warehouse::factory()->create();
    $this->user = User::factory()->create();
});

test('recordMovement creates a stock movement', function (): void {
    $movement = $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Receive,
        baseQuantity: 50,
        referenceCode: 'PO-001',
    );

    expect($movement)->toBeInstanceOf(StockMovement::class)
        ->and($movement->variant_id)->toBe($this->variant->id)
        ->and($movement->warehouse_id)->toBe($this->warehouse->id)
        ->and($movement->type)->toBe(MovementType::Receive)
        ->and($movement->quantity)->toBe(50)
        ->and($movement->reference_code)->toBe('PO-001');
});

test('recordMovement sets created_by from auth user', function (): void {
    $this->actingAs($this->user);

    $movement = $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->warehouse->id,
        type: MovementType::Receive,
        baseQuantity: 10,
    );

    expect($movement->created_by)->toBe($this->user->id);
});

test('currentQuantity returns sum of movements', function (): void {
    $this->service->recordMovement(variantId: $this->variant->id, warehouseId: $this->warehouse->id, type: MovementType::Receive, baseQuantity: 100);
    $this->service->recordMovement(variantId: $this->variant->id, warehouseId: $this->warehouse->id, type: MovementType::Ship, baseQuantity: -30);
    $this->service->recordMovement(variantId: $this->variant->id, warehouseId: $this->warehouse->id, type: MovementType::Adjustment, baseQuantity: 5);

    expect($this->service->currentQuantity($this->product->id, $this->warehouse->id))->toBe(75);
});

test('totalQuantity returns sum across all warehouses', function (): void {
    $warehouse2 = Warehouse::factory()->create();

    $this->service->recordMovement(variantId: $this->variant->id, warehouseId: $this->warehouse->id, type: MovementType::Receive, baseQuantity: 100);
    $this->service->recordMovement(variantId: $this->variant->id, warehouseId: $warehouse2->id, type: MovementType::Receive, baseQuantity: 50);

    expect($this->service->totalQuantity($this->product->id))->toBe(150);
});

test('ship deducts stock and creates movement', function (): void {
    $this->service->recordMovement(variantId: $this->variant->id, warehouseId: $this->warehouse->id, type: MovementType::Receive, baseQuantity: 100);

    $movement = $this->service->ship($this->product->id, $this->warehouse->id, 30, 'ORDER-001');

    expect($movement->type)->toBe(MovementType::Ship)
        ->and($movement->quantity)->toBe(-30)
        ->and($movement->reference_code)->toBe('ORDER-001')
        ->and($this->service->currentQuantity($this->product->id, $this->warehouse->id))->toBe(70);
});

test('ship throws exception on insufficient stock', function (): void {
    $this->service->recordMovement(variantId: $this->variant->id, warehouseId: $this->warehouse->id, type: MovementType::Receive, baseQuantity: 10);

    $this->service->ship($this->product->id, $this->warehouse->id, 20);
})->throws(InsufficientStockException::class);

test('ship throws exception on zero or negative quantity', function (): void {
    $this->service->recordMovement(variantId: $this->variant->id, warehouseId: $this->warehouse->id, type: MovementType::Receive, baseQuantity: 100);

    $this->service->ship($this->product->id, $this->warehouse->id, 0);
})->throws(InvalidArgumentException::class);

test('transfer creates linked movements in transaction', function (): void {
    $warehouse2 = Warehouse::factory()->create();
    $this->service->recordMovement(variantId: $this->variant->id, warehouseId: $this->warehouse->id, type: MovementType::Receive, baseQuantity: 100);

    [$outMovement, $inMovement] = $this->service->transfer(
        productId: $this->product->id,
        fromWarehouseId: $this->warehouse->id,
        toWarehouseId: $warehouse2->id,
        quantity: 40,
        reference: 'TRANSFER-001',
    );

    expect($outMovement->type)->toBe(MovementType::TransferOut)
        ->and($outMovement->quantity)->toBe(-40)
        ->and($outMovement->warehouse_id)->toBe($this->warehouse->id)
        ->and($inMovement->type)->toBe(MovementType::TransferIn)
        ->and($inMovement->quantity)->toBe(40)
        ->and($inMovement->warehouse_id)->toBe($warehouse2->id)
        ->and($inMovement->related_movement_id)->toBe($outMovement->id)
        ->and($this->service->currentQuantity($this->product->id, $this->warehouse->id))->toBe(60)
        ->and($this->service->currentQuantity($this->product->id, $warehouse2->id))->toBe(40);
});

test('transfer throws exception on same warehouse', function (): void {
    $this->service->transfer(
        productId: $this->product->id,
        fromWarehouseId: $this->warehouse->id,
        toWarehouseId: $this->warehouse->id,
        quantity: 10,
    );
})->throws(InvalidArgumentException::class);

test('transfer throws exception on zero or negative quantity', function (): void {
    $warehouse2 = Warehouse::factory()->create();

    $this->service->transfer(
        productId: $this->product->id,
        fromWarehouseId: $this->warehouse->id,
        toWarehouseId: $warehouse2->id,
        quantity: 0,
    );
})->throws(InvalidArgumentException::class);
