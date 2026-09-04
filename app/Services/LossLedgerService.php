<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\TransferRequisitionItem;
use Illuminate\Support\Facades\Auth;

final class LossLedgerService
{
    /**
     * Record a loss/damage entry in the LossLedger.
     */
    public function recordLoss(
        int $requisitionId,
        int $requisitionItemId,
        int $variantId,
        int $warehouseId,
        float $lostBaseQty,
        float $damagedBaseQty,
        string $lossCategory,
        ?int $userId = null
    ): LossLedger {
        // Get the variant to access its cost price
        $variant = ProductVariant::findOrFail($variantId);

        // Calculate total financial loss
        $totalLostQty = max(0, $lostBaseQty) + max(0, $damagedBaseQty);
        $totalFinancialLoss = $totalLostQty * $variant->cost_price;

        // Create the loss ledger entry
        return LossLedger::create([
            'requisition_id' => $requisitionId,
            'requisition_item_id' => $requisitionItemId,
            'variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'lost_base_qty' => max(0, $lostBaseQty),
            'damaged_base_qty' => max(0, $damagedBaseQty),
            'unit_cost_price' => $variant->cost_price,
            'total_financial_loss' => $totalFinancialLoss,
            'loss_category' => $lossCategory,
            'recorded_by' => $userId ?? Auth::id(),
            'recorded_at' => now(),
        ]);
    }

    /**
     * Record losses for a transfer requisition item based on received quantities.
     * This method mirrors the logic from InventoryService::scanToReceive() but
     * uses the service for LossLedger creation.
     *
     * @param  array  $receivedData  ['good_qty' => int, 'damaged_qty' => int, 'loss_category' => string|null]
     */
    public function recordItemLoss(
        TransferRequisitionItem $item,
        array $receivedData,
        ?int $userId = null
    ): void {
        $requisition = $item->transferRequisition;
        $ratio = $item->approved_unit_ratio ?? $item->requested_unit_ratio;

        $goodBase = $receivedData['good_qty'] * $ratio;
        $damagedBase = $receivedData['damaged_qty'] * $ratio;
        $expectedBase = $item->shipped_base_qty;
        $lostBase = $expectedBase - ($goodBase + $damagedBase);

        // Only record if there's actual loss or damage
        if ($lostBase > 0 || $damagedBase > 0) {
            $this->recordLoss(
                $requisition->id,
                $item->id,
                $item->variant_id,
                $requisition->to_warehouse_id,
                $lostBase,
                $damagedBase,
                $receivedData['loss_category'] ?? 'Transit Variance',
                $userId
            );
        }
    }
}
