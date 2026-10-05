<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class SalesOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.sales_orders.infolist.profile'))
                    ->icon(Heroicon::DocumentText)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('reference_code')->label(__('resources.sales_orders.fields.reference_code'))
                            ->weight(FontWeight::Bold)->size('lg')->copyable()->color('primary')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('status')->badge()
                            ->label(__('resources.sales_orders.fields.status'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('customer.name')->icon(Heroicon::UserGroup)
                            ->label(__('resources.sales_orders.fields.customer'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('warehouse.name')->icon(Heroicon::BuildingOffice2)
                            ->label(__('resources.sales_orders.fields.dispatching_warehouse'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('notes')
                            ->label(__('resources.sales_orders.fields.notes'))
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                            ->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.sales_orders.infolist.signoffs'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('orderedBy.name')->label(__('resources.sales_orders.fields.ordered_by'))->icon(Heroicon::User)->placeholder(__('common.empty')),
                        TextEntry::make('dispatchedBy.name')->label(__('resources.sales_orders.fields.dispatched_by'))->icon(Heroicon::Truck)->placeholder(__('common.empty')),
                        TextEntry::make('confirmed_at')->label(__('resources.sales_orders.fields.confirmed_at'))->dateTime('M j, Y H:i')->placeholder(__('common.empty')),
                        TextEntry::make('dispatched_at')->label(__('resources.sales_orders.fields.dispatched_at'))->dateTime('M j, Y H:i')->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.sales_orders.infolist.line_items'))
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->table([
                                TableColumn::make(__('resources.sales_orders.fields.sku')),
                                TableColumn::make(__('resources.sales_orders.fields.product')),
                                TableColumn::make(__('resources.sales_orders.fields.ordered_base')),
                                TableColumn::make(__('resources.sales_orders.fields.dispatched_base')),
                                TableColumn::make(__('resources.sales_orders.fields.snapshot_price')),
                            ])
                            ->schema([
                                TextEntry::make('productVariant.sku')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('productVariant.name'),

                                TextEntry::make('base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('dispatched_base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('unit_sale_price_snapshot')
                                    ->formatStateUsing(fn ($state): string => format_money($state))
                                    ->alignEnd(),
                            ]),
                    ]),
            ]),
        ]);
    }
}
