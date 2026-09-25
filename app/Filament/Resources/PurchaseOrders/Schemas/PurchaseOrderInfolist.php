<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make('PURCHASE ORDER PROFILE')
                    ->icon(Heroicon::DocumentText)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('reference_code')
                            ->label(__('resources.purchase_orders.fields.reference_code'))
                            ->weight(FontWeight::Bold)->size('lg')->copyable()->color('primary')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('status')->badge()
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('supplier.name')->icon(Heroicon::BuildingStorefront)
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('warehouse.name')->icon(Heroicon::BuildingOffice2)
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('update_cost_price')
                            ->badge()->boolean()
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),
                    ]),

                Section::make('SIGN-OFFS')
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('orderedBy.name')->label(__('resources.purchase_orders.fields.ordered_by'))->icon(Heroicon::User)->placeholder('—'),
                        TextEntry::make('receivedBy.name')->label(__('resources.purchase_orders.fields.received_by'))->icon(Heroicon::ArchiveBoxArrowDown)->placeholder('—'),
                        TextEntry::make('ordered_at')->label(__('resources.purchase_orders.fields.ordered_at'))->dateTime('M j, Y H:i')->placeholder('—'),
                        TextEntry::make('received_at')->label(__('resources.purchase_orders.fields.received_at'))->dateTime('M j, Y H:i')->placeholder('—'),
                    ]),

                Section::make('LINE ITEMS')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3, 'xl' => 6])->schema([
                                    TextEntry::make('productVariant.sku')->label(__('resources.purchase_orders.fields.sku'))->weight(FontWeight::Bold)
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('productVariant.name')->label(__('resources.purchase_orders.fields.product'))
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 2]),
                                    TextEntry::make('ordered_base_qty')->label(__('resources.purchase_orders.fields.ordered_base'))->numeric()
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('received_base_qty')->label(__('resources.purchase_orders.fields.received_base'))->numeric()
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('unit_cost_price')->money(config('app.currency'), decimals: 4)
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                ]),
                            ]),
                    ]),
            ]),
        ]);
    }
}
