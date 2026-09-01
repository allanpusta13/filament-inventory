<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Stock Transfer Note - {{ $requisition->reference_code }}</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 20px;
            color: #000;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .header .info {
            flex: 1;
        }
        .header .qr-code {
            text-align: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background-color: #f0f0f0;
        }
        .footer {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }
        .footer .signature {
            width: 45%;
            border-top: 1px solid #000;
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="info">
            <h1>Stock Transfer Note</h1>
            <p><strong>Reference Code:</strong> {{ $requisition->reference_code }}</p>
            <p><strong>From Warehouse:</strong> {{ $requisition->fromWarehouse->name ?? 'N/A' }} ({{ $requisition->fromWarehouse->code ?? 'N/A' }})</p>
            <p><strong>To Warehouse:</strong> {{ $requisition->toWarehouse->name ?? 'N/A' }} ({{ $requisition->toWarehouse->code ?? 'N/A' }})</p>
            <p><strong>Dispatch Date:</strong> {{ $requisition->dispatched_at ? $requisition->dispatched_at->format('Y-m-d H:i:s') : 'N/A' }}</p>
            <p><strong>Requested By:</strong> {{ $requisition->requestedBy->name ?? 'N/A' }}</p>
        </div>
        <div class="qr-code">
            {!! $qrCode !!}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Variant SKU</th>
                <th>Variant Name</th>
                <th>Shipping Unit</th>
                <th>Quantity (Shipping Unit)</th>
                <th>Quantity (Base Unit)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requisition->items as $item)
                <tr>
                    <td>{{ $item->variant->sku }}</td>
                    <td>{{ $item->variant->name }}</td>
                    <td>{{ $item->variant->base_unit_name }}</td>
                    <td>{{ $item->requested_base_qty }}</td>
                    <td>{{ $item->approved_base_qty ?? $item->requested_base_qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div class="signature">
            <p>Dispatching Manager</p>
            <br><br>
            <p>_____________________</p>
        </div>
        <div class="signature">
            <p>Receiving Manager</p>
            <br><br>
            <p>_____________________</p>
        </div>
    </div>
</body>
</html>