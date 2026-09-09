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
     * Display the scan-to-receive landing page with read-only dispatched items.
     */
    public function show(TransferRequisition $transferRequisition, Request $request)
    {
        // Validate signed URL (30-day expiry)
        if (! $request->hasValidSignature()) {
            session()->flash('notification', [
                'title' => 'Signature Expired or Invalid',
                'body' => 'The scanned Stock Transfer Note is older than 30 days or has been modified. Please generate a fresh manifest.',
                'type' => 'danger',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Restrict access: only users authorized at destination warehouse can access
        $user = Auth::user();
        if (! $user->hasAccessToWarehouse($transferRequisition->to_warehouse_id)) {
            session()->flash('notification', [
                'title' => 'Access Denied',
                'body' => 'You are not assigned to the destination warehouse linked to this transfer requisition.',
                'type' => 'warning',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Only allow scanning for requisitions that are dispatched or partially received
        if (! in_array($transferRequisition->status, [
            'dispatched',
            'partially_received',
        ])) {
            session()->flash('notification', [
                'title' => 'Invalid Status',
                'body' => 'This transfer requisition is not ready for receiving.',
                'type' => 'warning',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Pass the requisition to the view for displaying read-only dispatched items
        return view('scan-receive.show', [
            'requisition' => $transferRequisition,
        ]);
    }

    /**
     * Process the scan-to-receive reconciliation form submission.
     */
    public function receive(TransferRequisition $transferRequisition, Request $request)
    {
        // Validate signed URL
        if (! $request->hasValidSignature()) {
            session()->flash('notification', [
                'title' => 'Signature Expired or Invalid',
                'body' => 'The scanned Stock Transfer Note is older than 30 days or has been modified. Please generate a fresh manifest.',
                'type' => 'danger',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Restrict access: only users authorized at destination warehouse can access
        $user = Auth::user();
        if (! $user->hasAccessToWarehouse($transferRequisition->to_warehouse_id)) {
            session()->flash('notification', [
                'title' => 'Access Denied',
                'body' => 'You are not assigned to the destination warehouse linked to this transfer requisition.',
                'type' => 'warning',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Validate the request
        $validated = $request->validate([
            'received_items' => 'required|array',
            'received_items.*.item_id' => 'required|exists:requisition_items,id',
            'received_items.*.good_qty' => 'required|integer|min:0',
            'received_items.*.damaged_qty' => 'required|integer|min:0',
            'received_items.*.loss_category' => 'sometimes|string',
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

        // Execute the receiving transaction (reuses existing service method)
        app(InventoryService::class)->scanToReceive(
            $transferRequisition->id,
            $receivedData,
            auth()->id(),
            auth()->user()
        );

        session()->flash('notification', [
            'title' => 'Receiving Complete',
            'body' => "Transfer requisition {$transferRequisition->reference_code} has been successfully received.",
            'type' => 'success',
        ]);

        return redirect()->route('filament.admin.pages.dashboard');
    }
}
