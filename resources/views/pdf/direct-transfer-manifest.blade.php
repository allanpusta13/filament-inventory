<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Transfer — {{ $transfer->reference_code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #1a1a1a; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #1a1a1a; padding-bottom: 16px; }
        .header-left h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .header-left p { color: #666; font-size: 11px; }
        .header-right { text-align: right; }
        .header-right .ref { font-size: 16px; font-weight: 700; color: #2563eb; }
        .header-right .date { color: #666; font-size: 11px; margin-top: 4px; }
        .qr-section { text-align: center; margin-top: 8px; }
        .qr-section img, .qr-section svg { width: 120px; height: 120px; }
        .qr-section p { font-size: 9px; color: #999; margin-top: 2px; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .warehouses { display: flex; gap: 40px; margin-bottom: 24px; }
        .warehouse { flex: 1; }
        .warehouse h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #666; margin-bottom: 4px; }
        .warehouse .name { font-size: 14px; font-weight: 600; }
        .warehouse .location { color: #666; font-size: 11px; margin-top: 2px; }
        .warehouse .code { font-size: 11px; color: #999; }
        .info-row { display: flex; gap: 40px; margin-bottom: 16px; font-size: 11px; color: #666; }
        .info-row .field { flex: 1; }
        .info-row .field strong { color: #1a1a1a; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { background: #f9fafb; border: 1px solid #e5e7eb; padding: 8px 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
        td { border: 1px solid #e5e7eb; padding: 8px 12px; font-size: 12px; }
        .qty { text-align: right; font-variant-numeric: tabular-nums; }
        .notes { margin-bottom: 24px; padding: 12px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb; }
        .notes h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 4px; }
        .notes p { font-size: 12px; line-height: 1.5; }
        .signatures { display: flex; gap: 40px; margin-top: 40px; margin-bottom: 24px; }
        .signature { flex: 1; }
        .signature .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 4px; }
        .signature .line { border-top: 1px solid #1a1a1a; margin-top: 60px; padding-top: 8px; font-size: 11px; color: #666; }
        .signature .line .name { font-weight: 600; color: #1a1a1a; }
        .disclaimer { background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
        .disclaimer h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #374151; margin-bottom: 4px; }
        .disclaimer p { font-size: 10px; line-height: 1.6; color: #4b5563; }
        .footer { text-align: center; font-size: 9px; color: #999; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>Direct Transfer Note</h1>
            <p>Instant warehouse-to-warehouse transfer</p>
        </div>
        <div class="header-right">
            <div class="ref">{{ $transfer->reference_code }}</div>
            <div class="date">
                @if($transfer->executed_at)
                    Transferred: {{ $transfer->executed_at->format('M d, Y g:i A') }}
                @endif
            </div>
            <div style="margin-top: 8px;">
                <span class="status status-{{ $transfer->status->value }}">
                    {{ $transfer->status->getLabel() }}
                </span>
            </div>
            @if(isset($qrCode) && $qrCode)
                <div class="qr-section">
                    {!! $qrCode !!}
                    <p>Scan to verify</p>
                </div>
            @endif
        </div>
    </div>

    <div class="warehouses">
        <div class="warehouse">
            <h3>From (Source)</h3>
            <div class="name">{{ $transfer->fromWarehouse->name }}</div>
            <div class="code">{{ $transfer->fromWarehouse->code }}</div>
            @if($transfer->fromWarehouse->location)
                <div class="location">{{ $transfer->fromWarehouse->location }}</div>
            @endif
        </div>
        <div class="warehouse">
            <h3>To (Destination)</h3>
            <div class="name">{{ $transfer->toWarehouse->name }}</div>
            <div class="code">{{ $transfer->toWarehouse->code }}</div>
            @if($transfer->toWarehouse->location)
                <div class="location">{{ $transfer->toWarehouse->location }}</div>
            @endif
        </div>
    </div>

    <div class="info-row">
        <div class="field">
            <strong>Executed by:</strong> {{ $transfer->executedByUser?->name ?? '—' }}
        </div>
        <div class="field">
            <strong>Executed at:</strong> {{ $transfer->executed_at?->format('M d, Y g:i A') ?? '—' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>SKU</th>
                <th>Product</th>
                <th>Unit</th>
                <th class="qty">Quantity</th>
                <th class="qty">Base Qty</th>
                <th>Audit Reason</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transfer->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->variant->sku }}</td>
                    <td>{{ $item->variant->name }}</td>
                    <td>{{ $item->unit_name }}</td>
                    <td class="qty">{{ number_format($item->quantity, 0) }}</td>
                    <td class="qty">{{ number_format($item->base_quantity, 0) }}</td>
                    <td>{{ $item->audit_reason ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #999;">No items</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($transfer->notes)
        <div class="notes">
            <h3>Notes</h3>
            <p>{{ $transfer->notes }}</p>
        </div>
    @endif

    <div class="signatures">
        <div class="signature">
            <div class="label">Origin Warehouse Manager</div>
            <div class="line">
                <span class="name">{{ $transfer->executedByUser?->name ?? '__________________________' }}</span>
                <br>Signature / Date
            </div>
        </div>
        <div class="signature">
            <div class="label">Destination Warehouse Manager</div>
            <div class="line">
                <span class="name">__________________________</span>
                <br>Signature / Date
            </div>
        </div>
    </div>

    <div class="disclaimer">
        <h3>Paper Backup Disclaimer</h3>
        <p>
            This printed document serves as a <strong>secondary backup copy</strong> for physical verification purposes only.
            The electronic records maintained in the inventory management system remain the <strong>single source of truth</strong>
            for all stock movements, quantities, and transaction statuses. Printed signatures are supplementary and do not
            supersede the digital audit trail. In the event of any discrepancy between this printed manifest and the
            system records, the system records shall prevail.
        </p>
    </div>

    <div class="footer">
        Printed: {{ now()->format('M d, Y g:i A') }} &mdash; {{ $transfer->reference_code }}
        &mdash; {{ config('app.name', 'Inventory System') }}
    </div>
</body>
</html>
