<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Notifications\Notification;

/**
 * §22.3b. Database notification for `InventoryBelowReorderPoint`.
 *
 * Carries the variant + warehouse pair from the event. Resolves the
 * SKU and warehouse name lazily inside `toDatabase()` so the queued
 * payload stays serializable.
 */
class InventoryBelowReorderPointNotification extends Notification
{
    public function __construct(
        public readonly int $productVariantId,
        public readonly int $warehouseId,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $sku = ProductVariant::find($this->productVariantId)?->sku
            ?? (string) $this->productVariantId;
        $warehouse = Warehouse::find($this->warehouseId)?->name
            ?? (string) $this->warehouseId;

        return [
            'product_variant_id' => $this->productVariantId,
            'warehouse_id' => $this->warehouseId,
            'message' => __('notifications.inventory_below_reorder_point.body', [
                'sku' => $sku,
                'warehouse' => $warehouse,
            ]),
        ];
    }
}
