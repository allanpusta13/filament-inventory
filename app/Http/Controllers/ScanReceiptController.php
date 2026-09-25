<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TransferRequisition;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

final class ScanReceiptController extends Controller
{
    /**
     * Display scan-to-receive landing page read-only dispatched items.
     */
    public function show(TransferRequisition $transferRequisition, Request $request)
    {
        // Validate signed URL (7-day expiry per Principle 8)
        if (! $request->hasValidSignature()) {
            session()->flash('notification', [
                'title' => __('scan.signature_expired_title'),
                'body' => __('scan.signature_expired_body'),
                'type' => 'danger',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Restrict access: only users authorized at destination warehouse can access
        $user = Auth::user();
        if (! $user->canAccessWarehouse($transferRequisition->toWarehouse)) {
            session()->flash('notification', [
                'title' => __('scan.access_denied_title'),
                'body' => __('scan.access_denied_body'),
                'type' => 'warning',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Only allow scanning requisitions dispatched partially received
        if (! in_array($transferRequisition->status->value, [
            \App\Enums\TransferRequisitionStatus::Dispatched->value,
            \App\Enums\TransferRequisitionStatus::PartiallyReceived->value,
        ], true)) {
            session()->flash('notification', [
                'title' => __('scan.invalid_status_title'),
                'body' => __('scan.invalid_status_body'),
                'type' => 'warning',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Pass requisition view displaying read-only
        return view('scan-receive.show', [
            'transferRequisition' => $transferRequisition->load('items.productVariant'),
        ]);
    }

    /**
     * Handle scan-to-receive POST.
     */
    public function receive(TransferRequisition $transferRequisition, Request $request)
    {
        // Validate signed URL (7-day expiry per Principle 8)
        if (! $request->hasValidSignature()) {
            session()->flash('notification', [
                'title' => __('scan.signature_expired_title'),
                'body' => __('scan.signature_expired_body'),
                'type' => 'danger',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Restrict access: only users authorized at destination warehouse can access
        $user = Auth::user();
        if (! $user->canAccessWarehouse($transferRequisition->toWarehouse)) {
            session()->flash('notification', [
                'title' => __('scan.access_denied_title'),
                'body' => __('scan.access_denied_body'),
                'type' => 'warning',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Validate input
        $validated = $request->validate([
            'received_items' => 'required|array|min:1',
            'received_items.*.item_id' => 'required|integer|min:1',
            'received_items.*.good_qty' => 'required|integer|min:0',
            'received_items.*.damaged_qty' => 'required|integer|min:0',
            'received_items.*.loss_category' => 'nullable|string|in:theft,damage,spoilage,variance,unknown',
        ]);

        // Transform data for InventoryService::scanToReceive()
        $receivedData = [];
        foreach ($validated['received_items'] as $item) {
            $receivedData[$item['item_id']] = [
                'good_qty' => (int) $item['good_qty'],
                'damaged_qty' => (int) $item['damaged_qty'],
                'loss_category' => $item['loss_category'] ?? null,
            ];
        }

        // Execute receiving transaction (reuses existing service method)
        app(InventoryService::class)->scanToReceive(
            $transferRequisition->id,
            $receivedData
        );

        session()->flash('notification', [
            'title' => __('scan.receive_complete_title'),
            'body' => __('scan.receive_complete_body', ['reference_code' => $transferRequisition->reference_code]),
            'type' => 'success',
        ]);

        return redirect()->route('filament.admin.pages.dashboard');
    }
}
