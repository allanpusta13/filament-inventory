<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InventoryService
{
    public function recordMovement(
        int $productId,
        int $warehouseId,
        MovementType $type,
        int $quantity,
        ?string $reference = null,
        ?int $relatedMovementId = null,
    ): StockMovement {
        return StockMovement::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'quantity' => $quantity,
            'reference' => $reference,
            'related_movement_id' => $relatedMovementId,
        ]);
    }

    public function ship(
        int $productId,
        int $warehouseId,
        int $quantity,
        ?string $reference = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Ship quantity must be positive.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $reference): StockMovement {
            $available = $this->currentQuantity($productId, $warehouseId);

            if ($available < $quantity) {
                throw new InsufficientStockException(
                    productId: $productId,
                    warehouseId: $warehouseId,
                    requested: $quantity,
                    available: $available,
                );
            }

            return $this->recordMovement(
                productId: $productId,
                warehouseId: $warehouseId,
                type: MovementType::Ship,
                quantity: -$quantity,
                reference: $reference,
            );
        });
    }

    /**
     * @return array{StockMovement, StockMovement}
     */
    public function transfer(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $quantity,
        ?string $reference = null,
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Transfer quantity must be positive.');
        }

        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('Cannot transfer to the same warehouse.');
        }

        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $reference): array {
            $outMovement = $this->recordMovement(
                productId: $productId,
                warehouseId: $fromWarehouseId,
                type: MovementType::TransferOut,
                quantity: -$quantity,
                reference: $reference,
            );

            $inMovement = $this->recordMovement(
                productId: $productId,
                warehouseId: $toWarehouseId,
                type: MovementType::TransferIn,
                quantity: $quantity,
                reference: $reference,
                relatedMovementId: $outMovement->id,
            );

            return [$outMovement, $inMovement];
        });
    }

    public function currentQuantity(int $productId, int $warehouseId): int
    {
        return (int) StockMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    public function totalQuantity(int $productId): int
    {
        return (int) StockMovement::where('product_id', $productId)
            ->sum('quantity');
    }
}
