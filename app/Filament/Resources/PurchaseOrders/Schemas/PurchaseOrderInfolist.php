<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
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
                Section::make(__('resources.purchase_orders.infolist.profile'))
                    ->icon(Heroicon::DocumentText)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('reference_code')
                            ->label(__('resources.purchase_orders.fields.reference_code'))
                            ->weight(FontWeight::Bold)->size('lg')->copyable()->color('primary')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('status')->badge()
                            ->label(__('resources.purchase_orders.fields.status'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('supplier.name')->icon(Heroicon::BuildingStorefront)
                            ->label(__('resources.purchase_orders.fields.supplier'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('warehouse.name')->icon(Heroicon::BuildingOffice2)
                            ->label(__('resources.purchase_orders.fields.receiving_warehouse'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        // boolean() is scoped to Filament's icon components only.
                        // Text components render booleans via formatStateUsing() + color().
                        TextEntry::make('update_cost_price')
                            ->label(__('resources.purchase_orders.fields.update_cost_price'))
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? __('common.yes') : __('common.no'))
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),

                        TextEntry::make('notes')
                            ->label(__('resources.purchase_orders.fields.notes'))
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                            ->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.purchase_orders.infolist.signoffs'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('orderedBy.name')->label(__('resources.purchase_orders.fields.ordered_by'))->icon(Heroicon::User)->placeholder(__('common.empty')),
                        TextEntry::make('receivedBy.name')->label(__('resources.purchase_orders.fields.received_by'))->icon(Heroicon::ArchiveBoxArrowDown)->placeholder(__('common.empty')),
                        TextEntry::make('ordered_at')->label(__('resources.purchase_orders.fields.ordered_at'))->dateTime('M j, Y H:i')->placeholder(__('common.empty')),
                        TextEntry::make('received_at')->label(__('resources.purchase_orders.fields.received_at'))->dateTime('M j, Y H:i')->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.purchase_orders.infolist.line_items'))
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->table([
                                TableColumn::make(__('resources.purchase_orders.fields.sku')),
                                TableColumn::make(__('resources.purchase_orders.fields.product')),
                                TableColumn::make(__('resources.purchase_orders.fields.ordered_base')),
                                TableColumn::make(__('resources.purchase_orders.fields.received_base')),
                                TableColumn::make(__('resources.purchase_orders.fields.unit_cost')),
                            ])
                            ->schema([
                                TextEntry::make('productVariant.sku')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('productVariant.name'),

                                TextEntry::make('ordered_base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('received_base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('unit_cost_price')
                                    ->formatStateUsing(fn ($state): string => format_money($state))
                                    ->alignEnd(),
                            ]),
                    ]),
            ]),
        ]);
    }
}