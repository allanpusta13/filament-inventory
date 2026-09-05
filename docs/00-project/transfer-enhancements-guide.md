# Caduceus (v2.6) Transfer Enhancements: Summary Verification steps & Printable Manifests

This specification outlines the concrete frontend design and database service implementations to support:
1. **The "Review & Verify" Final Step** within both dialog-based transfer wizards (Transfer Requisitions and Direct Transfers) inside FilamentPHP v5.
2. **The Print-Optimized STN Manifest PDF Route and UI Actions** which allow operators to generate, download, and print highly readable transfer manifest sheets embedding secure signed scanning QR routing blocks.

---

## 🏛️ Part 1: Unified Dialog-Based Wizards with Summary Verification

By introducing a final **"Review & Verify"** step to both transfer wizards, we prevent human entry error before database records are committed. These steps utilize reactive state-bindings (`$get`) to render high-contrast summary tables dynamically inside the dialog panel.

To implement this layout, both schemas under the `app/Filament/Resources/` folders are modified:

### 1. Requisition Wizard Summary Schema (`app/Filament/Resources/TransferRequisitions/Schemas/TransferRequisitionForm.php`)

```php
namespace App\Filament\Resources\TransferRequisitions\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Illuminate\Support\HtmlString;
use App\Models\Warehouse;
use App\Models\ProductVariant;

class TransferRequisitionForm
{
    /**
     * Step 1: Location Mapping
     */
    public static function getRoutingSchema(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Select::make('from_warehouse_id')
                        ->label('ORIGIN WAREHOUSE (FULFILLER)')
                        ->relationship('fromWarehouse', 'name', fn ($query) => $query->where('is_active', true))
                        ->required()
                        ->searchable()
                        ->preload(),

                    Select::make('to_warehouse_id')
                        ->label('DESTINATION WAREHOUSE (REQUESTOR)')
                        ->relationship('toWarehouse', 'name', fn ($query) => $query->where('is_active', true))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->different('from_warehouse_id')
                        ->validationMessages([
                            'different' => 'The destination warehouse cannot be the same as the origin warehouse.',
                        ]),
                ]),
        ];
    }

    /**
     * Step 2: Material Manifest
     */
    public static function getItemsSchema(): array
    {
        return [
            Section::make('REQUESTED ITEMS MANIFEST')
                ->description('Specify product variants, packaging unit formats, and order metrics.')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Select::make('variant_id')
                                ->label('PRODUCT VARIANT')
                                ->relationship('variant', 'sku')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->columnSpan(3),

                            TextInput::make('requested_unit_name')
                                ->label('PACKAGING FORMAT')
                                ->required()
                                ->placeholder('E.g., Box')
                                ->columnSpan(2),

                            TextInput::make('requested_unit_ratio')
                                ->label('UNIT RATIO')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(1)
                                ->columnSpan(1),

                            TextInput::make('requested_qty')
                                ->label('ORDER QUANTITY')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->columnSpan(2),
                        ])
                        ->columns(8)
                        ->defaultItems(1)
                        ->extraAttributes(['class' => 'gap-y-4']),
                ])->compact(),
        ];
    }

    /**
     * Step 3: Review & Verify (Summary Checklist)
     */
    public static function getSummarySchema(): array
    {
        return [
            Section::make('REQUISITION SUMMARY CHECKLIST')
                ->description('Please review all routing and quantity entries below before submitting your formal requisition.')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Placeholder::make('summary_from_warehouse')
                                ->label('ORIGIN BRANCH (FULFILLER)')
                                ->content(fn ($get) => Warehouse::find($get('from_warehouse_id'))?->name ?? 'None Selected'),
                            Placeholder::make('summary_to_warehouse')
                                ->label('DESTINATION BRANCH (REQUESTOR)')
                                ->content(fn ($get) => Warehouse::find($get('to_warehouse_id'))?->name ?? 'None Selected'),
                        ])
                        ->extraAttributes(['class' => 'pb-4 border-b border-zinc-100']),

                    Placeholder::make('summary_items_manifest')
                        ->label('PROPOSED MATERIAL MANIFEST ITEMS')
                        ->content(function ($get) {
                            $items = $get('items') ?? [];
                            if (empty($items)) {
                                return new HtmlString('<p class="text-zinc-500 italic">No inventory lines declared in the manifest.</p>');
                            }

                            $html = '<div class="overflow-x-auto rounded-lg border border-zinc-200 mt-2">';
                            $html .= '<table class="w-full text-left text-xs uppercase tracking-wider text-zinc-800">';
                            $html .= '<thead>';
                            $html .= '<tr class="bg-zinc-50 border-b border-zinc-200">';
                            $html .= '<th class="py-2 px-3 font-semibold">Product Variant SKU</th>';
                            $html .= '<th class="py-2 px-3 font-semibold text-right">Order Qty</th>';
                            $html .= '<th class="py-2 px-3 font-semibold">Packaging Unit</th>';
                            $html .= '<th class="py-2 px-3 font-semibold text-right">Computed Base Quantity</th>';
                            $html .= '</tr>';
                            $html .= '</thead>';
                            $html .= '<tbody>';

                            foreach ($items as $item) {
                                if (empty($item['variant_id'])) continue;
                                $sku = ProductVariant::find($item['variant_id'])?->sku ?? 'Unknown SKU';
                                $qty = (int) ($item['requested_qty'] ?? 0);
                                $unit = $item['requested_unit_name'] ?? 'Base Unit';
                                $ratio = (int) ($item['requested_unit_ratio'] ?? 1);
                                $baseTotal = $qty * $ratio;

                                $html .= '<tr class="border-b border-zinc-100 hover:bg-zinc-50/50">';
                                $html .= '<td class="py-2 px-3 font-bold">' . e($sku) . '</td>';
                                $html .= '<td class="py-2 px-3 text-right">' . number_format($qty) . '</td>';
                                $html .= '<td class="py-2 px-3 text-zinc-500">' . e($unit) . ' (x' . $ratio . ')</td>';
                                $html .= '<td class="py-2 px-3 text-right font-semibold text-zinc-950">' . number_format($baseTotal) . ' Base Units</td>';
                                $html .= '</tr>';
                            }

                            $html .= '</tbody>';
                            $html .= '</table>';
                            $html .= '</div>';

                            return new HtmlString($html);
                        }),
                ])
                ->compact(),
        ];
    }
}
```

---

### 2. Direct Transfer Wizard Summary Schema (`app/Filament/Resources/DirectTransfers/Schemas/DirectTransferForm.php`)

```php
namespace App\Filament\Resources\DirectTransfers\Schemas;

use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use App\Models\Warehouse;
use App\Models\ProductVariant;

class DirectTransferForm
{
    /**
     * Step 1: Location Mapping
     */
    public static function getRoutingSchema(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Select::make('from_warehouse_id')
                        ->label('ORIGIN BRANCH (FULFILLER)')
                        ->relationship('fromWarehouse', 'name', fn ($q) => $q->where('is_active', true))
                        ->required()
                        ->searchable()
                        ->preload(),

                    Select::make('to_warehouse_id')
                        ->label('DESTINATION BRANCH (RECEIVER)')
                        ->relationship('toWarehouse', 'name', fn ($q) => $q->where('is_active', true))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->different('from_warehouse_id')
                        ->validationMessages([
                            'different' => 'The destination warehouse cannot be the same as the origin warehouse.',
                        ]),
                ]),
        ];
    }

    /**
     * Step 2: Stock Allocation
     */
    public static function getAllocationSchema(): array
    {
        return [
            Select::make('variant_id')
                ->label('PRODUCT VARIANT')
                ->relationship('variant', 'sku')
                ->required()
                ->searchable()
                ->preload(),

            TextInput::make('quantity')
                ->label('TRANSFER QUANTITY')
                ->numeric()
                ->required()
                ->minValue(1)
                ->default(1),

            Textarea::make('notes')
                ->label('AUDIT COMPLIANCE REASON')
                ->required()
                ->minLength(15)
                ->placeholder('Explain why this direct, immediate stock transfer is required...'),
        ];
    }

    /**
     * Step 3: Review & Verify Summary
     */
    public static function getSummarySchema(): array
    {
        return [
            Section::make('DIRECT TRANSFER VERIFICATION SUMMARY')
                ->description('Please review and confirm these balanced physical-to-digital movements before executing.')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Placeholder::make('summary_from_warehouse')
                                ->label('ORIGIN BRANCH (FULFILLER)')
                                ->content(fn ($get) => Warehouse::find($get('from_warehouse_id'))?->name ?? 'None Selected'),

                            Placeholder::make('summary_to_warehouse')
                                ->label('DESTINATION BRANCH (RECEIVER)')
                                ->content(fn ($get) => Warehouse::find($get('to_warehouse_id'))?->name ?? 'None Selected'),

                            Placeholder::make('summary_variant')
                                ->label('SELECTED VARIANT SKU')
                                ->content(fn ($get) => ProductVariant::find($get('variant_id'))?->sku ?? 'None Selected')
                                ->extraAttributes(['class' => 'font-semibold text-zinc-950']),

                            Placeholder::make('summary_quantity')
                                ->label('INSTANT TRANSFER VOLUME')
                                ->content(fn ($get) => number_format((int) ($get('quantity') ?? 0)) . ' Base Units')
                                ->extraAttributes(['class' => 'font-bold text-emerald-600']),
                        ])
                        ->extraAttributes(['class' => 'pb-4 border-b border-zinc-100']),

                    Placeholder::make('summary_notes')
                        ->label('AUDIT COMPLIANCE STATEMENT')
                        ->content(fn ($get) => $get('notes') ?? 'None Provided')
                        ->extraAttributes(['class' => 'italic text-zinc-600 font-serif']),
                ])
                ->compact(),
        ];
    }
}
```

---

## 🖨️ Part 2: Printable Manifest System (Printable PDF & Blade Template)

The system supports physical-to-digital handshakes by generating a clinical-standard **Stock Transfer Note (STN)** manifest. This printable sheet contains full metadata, a structured shipping grid of line items, sign-off blocks, and a **dynamically drawn SVG QR Code** encoding a 30-day temporary signed URL scan target.

### 1. Secure Manifest Download Controller (`app/Http/Controllers/STNManifestController.php`)

This controller receives requests, validates permissions via Laravel Policies, and renders a print-optimized, clean HTML view of the manifest which can be saved or directly printed using the browser’s clean print engine.

```php
namespace App\Http\Controllers;

use App\Models\TransferRequisition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class STNManifestController extends Controller
{
    /**
     * Renders a clean, print-optimized document layout of the transfer requisition.
     */
    public function print(Request $request, TransferRequisition $requisition)
    {
        // Enforce branch-scoped read policies (RBAC)
        $user = auth()->user();
        if (!$user->canAccessWarehouse($requisition->fromWarehouse) && !$user->canAccessWarehouse($requisition->toWarehouse)) {
            abort(403, 'Unauthorized location access.');
        }

        // Generate temporary 30-day secure signed URL route for the QR scan target
        $signedUrl = URL::temporarySignedRoute(
            'stn.scan',
            now()->addDays(30),
            ['transferRequisition' => $requisition->id]
        );

        // Render QR Code block in secure SVG format
        $qrCodeSvg = QrCode::size(120)
            ->color(24, 24, 27) // Clinical Charcoal
            ->backgroundColor(255, 255, 255)
            ->generate($signedUrl);

        return view('pdf.stn-manifest', [
            'requisition' => $requisition->load(['fromWarehouse', 'toWarehouse', 'requestedBy', 'items.variant']),
            'qrCode' => $qrCodeSvg,
            'signedUrl' => $signedUrl,
        ]);
    }
}
```

---

### 2. High-Contrast, Print-Optimized Layout (`resources/views/pdf/stn-manifest.blade.php`)

This template utilizes clean CSS grids, page-break safeguards, and hides standard UI chrome when using browser print actions (`@media print` rules). It displays all necessary metadata, item grids, and sign-offs in high contrast.

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STN MANIFEST: {{ $requisition->reference_code }}</title>
    <style>
        /* Base Reset & Instrument Sans Alignment */
        @import url('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap');
        
        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            color: #18181b;
            background-color: #ffffff;
            margin: 0;
            padding: 30px;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Print Specific Styles */
        @media print {
            body {
                padding: 0;
                font-size: 11px;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
        }

        /* Structured Layout Styling */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #18181b;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .brand-zone h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }

        .brand-zone p {
            color: #71717a;
            margin: 0;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .barcode-zone {
            text-align: right;
        }

        .barcode-zone .qr-container {
            display: inline-block;
            border: 1px solid #e4e4e7;
            padding: 5px;
            background: #ffffff;
        }

        .metadata-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            padding: 15px;
            background-color: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 4px;
        }

        .meta-card h3 {
            font-size: 10px;
            text-transform: uppercase;
            color: #71717a;
            letter-spacing: 0.05em;
            margin: 0 0 4px 0;
            font-weight: 600;
        }

        .meta-card p {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
        }

        /* High-Density Materials Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        th {
            background-color: #fafafa;
            border-bottom: 2px solid #e4e4e7;
            padding: 8px 12px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            color: #71717a;
            text-align: left;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e4e4e7;
            vertical-align: middle;
        }

        .sku-cell {
            font-weight: 700;
        }

        .qty-cell {
            text-align: right;
            font-weight: 600;
        }

        .sub-badge {
            display: inline-block;
            background-color: #fef3c7;
            color: #d97706;
            padding: 1px 6px;
            border-radius: 2px;
            font-size: 9px;
            font-weight: 600;
            margin-left: 8px;
            text-transform: uppercase;
        }

        /* Formal Double Sign-Off Zones */
        .signatures-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-top: 50px;
            page-break-inside: avoid;
        }

        .signature-box {
            border-top: 1px solid #18181b;
            padding-top: 10px;
        }

        .signature-box h4 {
            margin: 0 0 15px 0;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #71717a;
        }

        .signature-line {
            height: 40px;
        }

        .print-btn-bar {
            background-color: #18181b;
            color: #ffffff;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .print-btn {
            background-color: #3b82f6;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
        }

        .print-btn:hover {
            background-color: #2563eb;
        }
    </style>
</head>
<body>

    <!-- Print Control Tool Bar (Invisible during print actions) -->
    <div class="print-btn-bar no-print">
        <span>Stock Transfer Note Manifest Ready for Printing</span>
        <button class="print-btn" onclick="window.print()">Print Document</button>
    </div>

    <!-- Header Block -->
    <div class="header-container">
        <div class="brand-zone">
            <h1>Stock Transfer Note</h1>
            <p>Caduceus Inventory Engine • Official Dispatch Manifest</p>
        </div>
        <div class="barcode-zone">
            <div class="qr-container">
                {!! $qrCode !!}
            </div>
            <p style="margin:4px 0 0 0; font-size:8px; color:#71717a; text-transform:uppercase;">Scan to Receive (30D Valid)</p>
        </div>
    </div>

    <!-- Metadata Grid -->
    <div class="metadata-grid">
        <div class="meta-card">
            <h3>Reference Code</h3>
            <p>{{ $requisition->reference_code }}</p>
        </div>
        <div class="meta-card">
            <h3>Origin Branch</h3>
            <p>{{ $requisition->fromWarehouse->name }} ({{ $requisition->fromWarehouse->code }})</p>
        </div>
        <div class="meta-card">
            <h3>Destination Branch</h3>
            <p>{{ $requisition->toWarehouse->name }} ({{ $requisition->toWarehouse->code }})</p>
        </div>
        <div class="meta-card">
            <h3>Status</h3>
            <p style="text-transform:uppercase;">{{ str_replace('_', ' ', $requisition->status) }}</p>
        </div>
        <div class="meta-card">
            <h3>Submitted Date</h3>
            <p>{{ $requisition->requested_at?->format('M d, Y H:i') ?? 'N/A' }}</p>
        </div>
        <div class="meta-card">
            <h3>Authorizing Operator</h3>
            <p>{{ $requisition->requestedBy->name }}</p>
        </div>
    </div>

    <!-- Material Manifest Shipping Grid -->
    <table>
        <thead>
            <tr>
                <th>Product Variant Item & SKU</th>
                <th class="qty-cell">Approved Package Qty</th>
                <th>Packaging format</th>
                <th class="qty-cell">Base Unit Equivalent</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requisition->items as $item)
                @php
                    $isSubstituted = !empty($item->substitute_variant_id);
                    $actualVariant = $isSubstituted ? $item->substituteVariant : $item->variant;
                    $qty = $item->approved_qty ?? $item->requested_qty;
                    $unit = $item->approved_unit_name ?? $item->requested_unit_name;
                    $ratio = $item->approved_unit_ratio ?? $item->requested_unit_ratio;
                    $baseQty = $qty * $ratio;
                @endphp
                <tr>
                    <td>
                        <span class="sku-cell">{{ $actualVariant->sku }}</span> - {{ $actualVariant->name }}
                        @if($isSubstituted)
                            <span class="sub-badge">Substitute Variant Swap</span>
                        @endif
                    </td>
                    <td class="qty-cell">{{ number_format($qty) }}</td>
                    <td style="color:#71717a;">{{ $unit }} (x{{ $ratio }})</td>
                    <td class="qty-cell font-bold">{{ number_format($baseQty) }} {{ $actualVariant->base_unit_name }}s</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Signature Handshake Zones -->
    <div class="signatures-container">
        <div class="signature-box">
            <h4>Dispatch Authorization (Origin Branch Manager)</h4>
            <div class="signature-line"></div>
            <p style="margin:0; font-size:11px;">Signed: ___________________________</p>
            <p style="margin:4px 0 0 0; font-size:10px; color:#71717a;">Date: ____/____/________</p>
        </div>
        <div class="signature-box">
            <h4>Intake Verification (Receiving Branch Manager)</h4>
            <div class="signature-line"></div>
            <p style="margin:0; font-size:11px;">Signed: ___________________________</p>
            <p style="margin:4px 0 0 0; font-size:10px; color:#71717a;">Date: ____/____/________</p>
        </div>
    </div>

</body>
</html>
```

---

## 🔗 Part 3: Registering Actions on Filament v5 View Page

To make this manifest printout directly actionable, register a secondary print button inside **`app/Filament/Resources/TransferRequisitions/Pages/ViewTransferRequisition.php`** using Fllament's clean Action bar:

```php
namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewTransferRequisition extends ViewRecord
{
    protected static string $resource = TransferRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Print Manifest Action Button
            Action::make('printSTN')
                ->label('Print STN Manifest')
                ->icon('heroicon-o-printer')
                ->color('gray') // Enforces "secondary gray action button" guideline
                ->url(fn ($record) => route('requisitions.print-manifest', ['requisition' => $record->id]))
                ->openUrlInNewTab(),
        ];
    }
}
```

---

### 🛡️ Why This Architecture Secures Floor Operations

1. **Reactive Accuracy**: Rendering the dynamic items summary as Step 3 before submission means workers have a final verification layout of expected item counts, packaging units, and the exact destination, eliminating accidental transfer mistakes.
2. **Zero-Overhead Manifest Printing**: Shifting print rendering to a web-based Blade layout using `@media print` CSS overrides ensures high performance. The browser print engine prints cleanly to standard thermal sheets or paper without loading slow server-side PDF compilers.
3. **Traceable Signed Verification**: The QR-encoded route on the physical paper integrates physical verification with digital security. Scanning the printed sheet handles RBAC checks and opens the Scan-to-Receive dialog directly, creating an airtight, end-to-end chain of custody.
