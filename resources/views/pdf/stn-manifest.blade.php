<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Transfer Note — {{ $requisition->reference_code }}</title>
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
        .qr-section img, .qr-section svg { width: 100px; height: 100px; }
        .qr-section p { font-size: 9px; color: #999; margin-top: 2px; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
        .status-draft { background: #f3f4f6; color: #374151; }
        .status-requested { background: #fef3c7; color: #92400e; }
        .status-under_review_fulfiller, .status-under_review_requestor { background: #dbeafe; color: #1d4ed8; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-dispatched { background: #dbeafe; color: #1d4ed8; }
        .status-partially_received { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-closed_with_loss { background: #fee2e2; color: #991b1b; }
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
        .signatures { display: flex; gap: 40px; margin-top: 40px; }
        .signature { flex: 1; }
        .signature .line { border-top: 1px solid #1a1a1a; margin-top: 50px; padding-top: 8px; font-size: 11px; color: #666; }
        .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>Stock Transfer Note</h1>
            <p>Branch-to-branch inventory transfer</p>
        </div>
        <div class="header-right">
            <div class="ref">{{ $requisition->reference_code }}</div>
            <div class="date">
                @if($requisition->dispatched_at)
                    Dispatched: {{ $requisition->dispatched_at->format('M d, Y g:i A') }}<br>
                @endif
                @if($requisition->completed_at)
                    Completed: {{ $requisition->completed_at->format('M d, Y g:i A') }}
                @endif
            </div>
            <div style="margin-top: 8px;">
                <span class="status status-{{ $requisition->status->value }}">
                    {{ $requisition->status->getLabel() }}
                </span>
            </div>
            @if(isset($qrCode) && $qrCode)
                <div class="qr-section">
                    {!! $qrCode !!}
                    <p>Scan to receive</p>
                </div>
            @endif
        </div>
    </div>

    <div class="warehouses">
        <div class="warehouse">
            <h3>From (Sender)</h3>
            <div class="name">{{ $requisition->fromWarehouse->name }}</div>
            <div class="code">{{ $requisition->fromWarehouse->code }}</div>
            @if($requisition->fromWarehouse->location)
                <div class="location">{{ $requisition->fromWarehouse->location }}</div>
            @endif
        </div>
        <div class="warehouse">
            <h3>To (Receiver)</h3>
            <div class="name">{{ $requisition->toWarehouse->name }}</div>
            <div class="code">{{ $requisition->toWarehouse->code }}</div>
            @if($requisition->toWarehouse->location)
                <div class="location">{{ $requisition->toWarehouse->location }}</div>
            @endif
        </div>
    </div>

    <div class="info-row">
        <div class="field">
            <strong>Requested by:</strong> {{ $requisition->requestedBy?->name ?? '—' }}
        </div>
        <div class="field">
            <strong>Requested at:</strong> {{ $requisition->requested_at?->format('M d, Y g:i A') ?? '—' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>SKU</th>
                <th>Product</th>
                <th>Unit</th>
                <th class="qty">Requested</th>
                <th class="qty">Approved</th>
                <th class="qty">Shipped</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requisition->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->variant->sku }}</td>
                    <td>{{ $item->variant->name }}</td>
                    <td>{{ $item->approved_unit_name ?? $item->variant->base_unit_name }}</td>
                    <td class="qty">{{ number_format($item->requested_base_qty, 0) }}</td>
                    <td class="qty">{{ number_format($item->approved_base_qty, 0) }}</td>
                    <td class="qty">{{ number_format($item->shipped_base_qty ?? 0, 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #999;">No items</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($requisition->notes)
        <div class="notes">
            <h3>Notes</h3>
            <p>{{ $requisition->notes }}</p>
        </div>
    @endif

    <div class="signatures">
        <div class="signature">
            <div class="line">
                Dispatched by: {{ $requisition->dispatchedBy?->name ?? '—' }}
            </div>
        </div>
        <div class="signature">
            <div class="line">
                Received by: {{ $requisition->receivedBy?->name ?? '—' }}
            </div>
        </div>
    </div>

    <div class="footer">
        Printed: {{ now()->format('M d, Y g:i A') }} &mdash; {{ $requisition->reference_code }}
    </div>
</body>
</html>
