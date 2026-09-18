<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Schemas;

use App\Filament\Components\WizardReviewStep;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class TransferRequisitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * Step 1: Warehouse Location Routing
     */
    public static function getRoutingSchema(): array
    {
        return [
            Select::make('from_warehouse_id')
                ->label('ORIGIN WAREHOUSE (FULFILLER)')
                ->options(fn () => Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->prefixIcon(Heroicon::BuildingOffice),

            Select::make('to_warehouse_id')
                ->label('DESTINATION WAREHOUSE (REQUESTOR)')
                ->options(fn () => Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->different('from_warehouse_id') // Prevents self-transfer routing
                ->validationMessages([
                    'different' => 'Destination warehouse cannot match the origin warehouse.',
                ])
                ->prefixIcon(Heroicon::BuildingOffice2),
        ];
    }

    /**
     * Step 2: Line Items & Packaging Formats
     */
    public static function getItemsSchema(): array
    {
        return [
            Section::make('REQUESTED MATERIAL MANIFEST')
                ->icon(Heroicon::ClipboardDocumentList)
                ->description('Select SKU variants, packaging formats, conversion ratios, and quantities.')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Select::make('product_variant_id')
                                ->label('PRODUCT VARIANT (SKU)')
                                ->relationship('productVariant', 'sku')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->columnSpan(3),

                            TextInput::make('requested_unit_name')
                                ->label('PACKAGING FORMAT')
                                ->required()
                                ->placeholder('Box')
                                ->columnSpan(2),

                            TextInput::make('requested_unit_ratio')
                                ->label('UNIT RATIO')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(1)
                                ->columnSpan(1)
                                ->helperText('Base units per package.'),

                            TextInput::make('requested_qty')
                                ->label('ORDER QUANTITY')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(1)
                                ->columnSpan(2),
                        ])
                        ->columns(8)
                        ->defaultItems(1),
                ]),
        ];
    }

    /**
     * Step 3: Review & Confirm
     */
    public static function getReviewSchema(): array
    {
        return [
            WizardReviewStep::make('review_summary')
                ->label('REVIEW & CONFIRM')
                ->content(fn (Get $get) => self::buildReviewHtml(
                    Warehouse::find($get('from_warehouse_id')),
                    Warehouse::find($get('to_warehouse_id')),
                    $get('items') ?? [],
                )),
        ];
    }

    /**
     * Build the review HTML for the wizard review step.
     */
    protected static function buildReviewHtml(?Warehouse $fromWarehouse, ?Warehouse $toWarehouse, array $items): HtmlString
    {
        if (! $fromWarehouse || ! $toWarehouse || empty($items)) {
            return new HtmlString('Complete previous steps to construct the verification sheet.');
        }

        $rowsHtml = '';
        foreach ($items as $item) {
            $variant = ProductVariant::find($item['product_variant_id'] ?? null);
            $sku = $variant?->sku ?? 'Unknown';
            $format = $item['requested_unit_name'] ?? 'Base Unit';
            $ratio = (int) ($item['requested_unit_ratio'] ?? 1);
            $qty = (int) ($item['requested_qty'] ?? 0);
            $totalBase = $qty * $ratio;

            $rowsHtml .= "
                <tr class='border-b border-zinc-200 dark:border-zinc-800'>
                    <td class='py-2 font-mono text-xs font-bold text-primary-600'>{$sku}</td>
                    <td class='py-2 text-xs'>{$format}</td>
                    <td class='py-2 text-xs text-right'>1 : {$ratio}</td>
                    <td class='py-2 text-xs text-right'>{$qty}</td>
                    <td class='py-2 text-xs text-right font-semibold text-zinc-900 dark:text-zinc-100'>{$totalBase} Pcs</td>
                </tr>";
        }

        return new HtmlString("
            <div class='grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-zinc-200 dark:border-zinc-800'>
                <div>
                    <span class='text-[10px] uppercase font-bold text-zinc-500'>Fulfilling Origin</span>
                    <p class='text-sm font-semibold text-zinc-900 dark:text-zinc-100'>{$fromWarehouse->name}</p>
                </div>
                <div>
                    <span class='text-[10px] uppercase font-bold text-zinc-500'>Receiving Destination</span>
                    <p class='text-sm font-semibold text-zinc-900 dark:text-zinc-100'>{$toWarehouse->name}</p>
                </div>
            </div>
            <table class='w-full text-left'>
                <thead>
                    <tr class='border-b border-zinc-300 dark:border-zinc-700'>
                        <th class='pb-2 text-[10px] uppercase font-bold text-zinc-500'>SKU</th>
                        <th class='pb-2 text-[10px] uppercase font-bold text-zinc-500'>Packaging</th>
                        <th class='pb-2 text-[10px] uppercase font-bold text-zinc-500 text-right'>Ratio</th>
                        <th class='pb-2 text-[10px] uppercase font-bold text-zinc-500 text-right'>Qty</th>
                        <th class='pb-2 text-[10px] uppercase font-bold text-zinc-500 text-right'>Computed Base</th>
                    </tr>
                </thead>
                <tbody>{$rowsHtml}</tbody>
            </table>
        ");
    }
}
