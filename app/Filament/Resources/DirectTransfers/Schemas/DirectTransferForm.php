<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Schemas;

use App\Filament\Components\WizardReviewStep;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class DirectTransferForm
{
    /**
     * Step 1: Location Mapping
     */
    public static function getLocationSchema(): array
    {
        return [
            Select::make('from_warehouse_id')
                ->label('ORIGIN WAREHOUSE')
                ->options(fn () => Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->default(fn () => auth()->user()->warehouses()->count() === 1
                    ? auth()->user()->warehouses()->first()->id
                    : null)
                ->prefixIcon(\Filament\Support\Icons\Heroicon::BuildingOffice),

            Select::make('to_warehouse_id')
                ->label('DESTINATION WAREHOUSE')
                ->options(fn () => Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->different('from_warehouse_id')
                ->validationMessages([
                    'different' => 'Destination warehouse cannot match origin warehouse.',
                ])
                ->prefixIcon(\Filament\Support\Icons\Heroicon::BuildingOffice2),
        ];
    }

    /**
     * Step 2: Stock Allocation
     */
    public static function getAllocationSchema(): array
    {
        return [
            Select::make('product_variant_id')
                ->label('PRODUCT VARIANT')
                ->relationship('productVariant', 'sku')
                ->required()
                ->searchable()
                ->preload()
                ->getOptionLabelFromRecordUsing(fn (ProductVariant $v) => "{$v->sku} - {$v->name}"),

            TextInput::make('quantity')
                ->label('BASE UNITS TO TRANSFER')
                ->numeric()
                ->minValue(1)
                ->required()
                ->placeholder('e.g., 100'),

            Textarea::make('notes')
                ->label('AUDIT NOTES')
                ->placeholder('Reason for transfer, approval ref, etc.')
                ->columnSpanFull()
                ->minLength(15)
                ->maxLength(500),
        ];
    }

    /**
     * Step 3: Review & Confirm
     */
    public static function getReviewSchema(): array
    {
        return [
            WizardReviewStep::make('review_summary')
                ->label('REVIEW & VERIFY')
                ->content(fn (Get $get) => self::buildReviewHtml(
                    Warehouse::find($get('from_warehouse_id')),
                    Warehouse::find($get('to_warehouse_id')),
                    ProductVariant::find($get('product_variant_id')),
                    $get('quantity'),
                    $get('notes'),
                )),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Wizard::make([
                    \Filament\Schemas\Components\Wizard\Step::make('Location Mapping')
                        ->description('Select origin and destination warehouses')
                        ->schema(self::getLocationSchema()),

                    \Filament\Schemas\Components\Wizard\Step::make('Stock Allocation')
                        ->description('Select variant and quantity to transfer')
                        ->schema(self::getAllocationSchema()),

                    \Filament\Schemas\Components\Wizard\Step::make('Review & Confirm')
                        ->description('Verify all details before executing transfer')
                        ->schema(self::getReviewSchema()),
                ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Build the review HTML for the wizard review step.
     */
    protected static function buildReviewHtml(
        ?Warehouse $fromWarehouse,
        ?Warehouse $toWarehouse,
        ?ProductVariant $productVariant,
        ?int $quantity,
        ?string $notes
    ): HtmlString {
        if (! $fromWarehouse || ! $toWarehouse || ! $productVariant || ! $quantity) {
            return new HtmlString('Complete previous steps to construct the verification sheet.');
        }

        $notesHtml = $notes ?? '&mdash;';

        return new HtmlString("
            <div class='grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-zinc-200 dark:border-zinc-800'>
                <div>
                    <span class='text-[10px] uppercase font-bold text-zinc-500'>ORIGIN</span>
                    <p class='text-sm font-semibold text-zinc-900 dark:text-zinc-100'>{$fromWarehouse->name}</p>
                </div>
                <div>
                    <span class='text-[10px] uppercase font-bold text-zinc-500'>DESTINATION</span>
                    <p class='text-sm font-semibold text-zinc-900 dark:text-zinc-100'>{$toWarehouse->name}</p>
                </div>
            </div>
            <div class='overflow-x-auto'>
                <table class='w-full text-left'>
                    <thead>
                        <tr class='border-b border-zinc-300 dark:border-zinc-700'>
                            <th class='pb-2 font-bold text-zinc-500'>SKU</th>
                            <th class='pb-2 font-bold text-zinc-500'>VARIANT</th>
                            <th class='pb-2 font-bold text-zinc-500 text-right'>BASE UNITS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class='border-b border-zinc-200 dark:border-zinc-800'>
                            <td class='py-2 font-mono text-xs font-bold text-primary-600'>{$productVariant->sku}</td>
                            <td class='py-2 text-xs'>{$productVariant->name}</td>
                            <td class='py-2 text-xs text-right font-semibold text-zinc-900 dark:text-zinc-100'>{$quantity} Pcs</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class='mt-4 p-3 rounded-lg bg-zinc-100 dark:bg-zinc-800'>
                <span class='text-[10px] uppercase font-bold text-zinc-500'>NOTES</span>
                <p class='text-sm text-zinc-700 dark:text-zinc-300'>{$notesHtml}</p>
            </div>
        ");
    }
}
