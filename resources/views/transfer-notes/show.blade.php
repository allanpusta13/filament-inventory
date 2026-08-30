<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Transfer Note — {{ $order->reference_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #1a1a1a; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #1a1a1a; padding-bottom: 16px; }
        .header-left h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .header-left p { color: #666; font-size: 11px; }
        .header-right { text-align: right; }
        .header-right .ref { font-size: 16px; font-weight: 700; color: #2563eb; }
        .header-right .date { color: #666; font-size: 11px; margin-top: 4px; }
        .branches { display: flex; gap: 40px; margin-bottom: 24px; }
        .branch { flex: 1; }
        .branch h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #666; margin-bottom: 4px; }
        .branch .name { font-size: 14px; font-weight: 600; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
        .status-draft { background: #f3f4f6; color: #374151; }
        .status-requested { background: #fef3c7; color: #92400e; }
        .status-under_review_fulfiller, .status-under_review_requestor { background: #dbeafe; color: #1d4ed8; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-dispatched { background: #dbeafe; color: #1d4ed8; }
        .status-received { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .driver-info { display: flex; gap: 40px; margin-bottom: 24px; padding: 12px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb; }
        .driver-info .field { flex: 1; }
        .driver-info .field h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { background: #f9fafb; border: 1px solid #e5e7eb; padding: 8px 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
        td { border: 1px solid #e5e7eb; padding: 8px 12px; }
        .qty { text-align: right; font-variant-numeric: tabular-nums; }
        .notes { margin-bottom: 24px; padding: 12px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb; }
        .notes h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 4px; }
        .audit-log { margin-bottom: 24px; }
        .audit-log h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 8px; }
        .audit-entry { padding: 8px; border-left: 3px solid #e5e7eb; margin-bottom: 8px; font-size: 11px; }
        .audit-entry .action { font-weight: 600; }
        .audit-entry .user { color: #6b7280; }
        .audit-entry .time { color: #9ca3af; }
        .signatures { display: flex; gap: 40px; margin-top: 40px; }
        .signature { flex: 1; }
        .signature .line { border-top: 1px solid #1a1a1a; margin-top: 50px; padding-top: 8px; font-size: 11px; color: #666; }
        .qr { margin-top: 16px; text-align: center; }
        .qr img { width: 80px; height: 80px; }
        .qr p { font-size: 9px; color: #999; margin-top: 4px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
            Print / Save as PDF
        </button>
    </div>

    <div class="header">
        <div class="header-left">
            <h1>Stock Transfer Note</h1>
            <p>Branch-to-branch inventory transfer</p>
        </div>
        <div class="header-right">
            <div class="ref">{{ $order->reference_number }}</div>
            <div class="date">
                @if($order->dispatched_at)
                    Dispatched: {{ $order->dispatched_at->format('M d, Y g:i A') }}
                @endif
                @if($order->received_at)
                    <br>Received: {{ $order->received_at->format('M d, Y g:i A') }}
                @endif
            </div>
            <div style="margin-top: 8px;">
                <span class="status status-{{ $order->status->value }}">
                    {{ $order->status->getLabel() }}
                </span>
            </div>
        </div>
    </div>

    <div class="branches">
        <div class="branch">
            <h3>From (Sender)</h3>
            <div class="name">{{ $order->sender->name }}</div>
            @if($order->sender->location)
                <div style="color: #666; font-size: 11px;">{{ $order->sender->location }}</div>
            @endif
        </div>
        <div class="branch">
            <h3>To (Receiver)</h3>
            <div class="name">{{ $order->receiver->name }}</div>
            @if($order->receiver->location)
                <div style="color: #666; font-size: 11px;">{{ $order->receiver->location }}</div>
            @endif
        </div>
    </div>

    @if($order->driver_name || $order->vehicle_plate)
        <div class="driver-info">
            <div class="field">
                <h3>Driver Name</h3>
                <div>{{ $order->driver_name ?? '—' }}</div>
            </div>
            <div class="field">
                <h3>Vehicle Plate</h3>
                <div>{{ $order->vehicle_plate ?? '—' }}</div>
            </div>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>SKU</th>
                <th class="qty">Requested</th>
                <th class="qty">Approved</th>
                <th class="qty">Received</th>
                <th class="qty">Damaged</th>
                <th class="qty">Lost</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->product->sku }}</td>
                    <td class="qty">{{ $item->requested_quantity }}</td>
                    <td class="qty">{{ $item->approved_quantity ?? '—' }}</td>
                    <td class="qty">{{ $item->received_quantity ?? '—' }}</td>
                    <td class="qty">{{ $item->damaged_quantity ?? 0 }}</td>
                    <td class="qty">{{ $item->lost_quantity ?? '—' }}</td>
                    <td>{{ $item->item_status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; color: #999;">No items</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($order->notes)
        <div class="notes">
            <h3>Notes</h3>
            <p>{{ $order->notes }}</p>
        </div>
    @endif

    @if($order->audits->count() > 0)
        <div class="audit-log">
            <h3>Audit Trail</h3>
            @foreach($order->audits->sortByDesc('created_at') as $audit)
                <div class="audit-entry">
                    <span class="action">{{ ucfirst($audit->action) }}</span>
                    <span class="user">by {{ $audit->user->name ?? 'Unknown' }}</span>
                    <span class="time">at {{ $audit->created_at->format('M d, Y g:i A') }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="qr">
        {!! $qrCode !!}
        <p>Scan to verify transfer</p>
    </div>

    <div class="signatures">
        <div class="signature">
            <div class="line">
                Dispatched by: {{ $order->dispatchedBy?->name ?? '—' }}
            </div>
        </div>
        <div class="signature">
            <div class="line">
                Received by: {{ $order->receivedBy?->name ?? '—' }}
            </div>
        </div>
    </div>
</body>
</html>
