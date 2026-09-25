<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class LossLedgerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 2, 'xl' => 2])->schema([
                Section::make('LOSS RECORD')
                    ->icon(Heroicon::ExclamationTriangle)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('recorded_at')->dateTime('M j, Y H:i')->columnSpanFull(),
                        TextEntry::make('transferRequisition.reference_code')->label(__('resources.loss_ledgers.fields.requisition'))->columnSpanFull(),
                        TextEntry::make('productVariant.sku')->label(__('resources.loss_ledgers.fields.sku'))->columnSpanFull(),
                        TextEntry::make('warehouse.name')->label(__('resources.loss_ledgers.fields.warehouse'))->columnSpanFull(),
                        TextEntry::make('loss_category')->badge()->columnSpanFull(),
                    ]),

                Section::make('FINANCIAL IMPACT')
                    ->icon(Heroicon::CurrencyDollar)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('lost_base_qty')->label(__('resources.loss_ledgers.fields.lost_base'))->numeric()->columnSpanFull(),
                        TextEntry::make('damaged_base_qty')->label(__('resources.loss_ledgers.fields.damaged_base'))->numeric()->columnSpanFull(),
                        TextEntry::make('unit_cost_price')
                            ->money(config('app.currency'), decimals: 4)->columnSpanFull(),
                        TextEntry::make('total_financial_loss')
                            ->money(config('app.currency'), decimals: 4)
                            ->weight('bold')->columnSpanFull(),
                    ]),
            ]),
        ]);
    }
}
