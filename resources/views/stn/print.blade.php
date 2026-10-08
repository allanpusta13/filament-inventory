{{-- resources/views/stn/print.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('stn.print.title', ['ref' => $requisition->reference_code]) }}</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: left; font-size: 13px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <h1>{{ __('stn.print.heading') }} — <span style="font-family: monospace;">{{ $requisition->reference_code }}</span></h1>
    <p>{{ __('stn.print.route', ['from' => $requisition->fromWarehouse->name, 'to' => $requisition->toWarehouse->name]) }}</p>
    <p>{{ __('stn.print.status', ['status' => $requisition->status->getLabel()]) }}</p>
    <div>
        {{-- Scannable QR (Phase 00.4 `simplesoftwareio/simple-qrcode`): the payload is the 7-day temporary signed scan URL itself (§21.2, no stored QR-payload column). --}}
        @php($signedScanUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('stn.scan', now()->addDays(7), ['transferRequisition' => $requisition->getKey()]))
        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($signedScanUrl) !!}
    </div>
    <table>
        <thead>
            <tr>
                <th>{{ __('stn.print.sku') }}</th>
                <th>{{ __('stn.print.variant') }}</th>
                <th>{{ __('stn.print.qty') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($requisition->items as $item)
                <tr>
                    <td style="font-family: monospace;">{{ $item->productVariant->sku }}</td>
                    <td>{{ $item->productVariant->name }}</td>
                    <td>{{ $item->approved_base_qty ?? $item->requested_base_qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button class="no-print" onclick="window.print()">{{ __('stn.print.print_button') }}</button>
</body>
</html>
