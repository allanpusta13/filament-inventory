<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

/**
 * Customer infolist — §7J / §0A.3 canonical contract.
 *
 * Rewritten from a user-authored version that used bare __() keys
 * (ADDRESS, Active, EMAIL, ...). Every label now resolves through the
 * resources.customers.* or common.* namespaces.
 */
class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.customers.infolist.profile'))
                    ->icon(Heroicon::UserGroup)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('resources.customers.fields.name'))
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),

                        TextEntry::make('contact_person')
                            ->label(__('resources.customers.fields.contact_person'))
                            ->icon(Heroicon::User)
                            ->placeholder(__('common.not_provided'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('email')
                            ->label(__('resources.customers.fields.email'))
                            ->icon(Heroicon::Envelope)
                            ->copyable()
                            ->placeholder(__('common.not_provided'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('phone')
                            ->label(__('resources.customers.fields.phone'))
                            ->icon(Heroicon::Phone)
                            ->copyable()
                            ->placeholder(__('common.not_provided'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('address')
                            ->label(__('resources.customers.fields.address'))
                            ->icon(Heroicon::MapPin)
                            ->placeholder(__('common.not_provided'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('is_active')
                            ->label(__('resources.customers.table.status'))
                            ->badge()
                            ->color(fn (bool $state) => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn (bool $state) => $state
                                ? __('common.active')
                                : __('common.inactive'))
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),
                    ]),

                Section::make(__('resources.customers.table.sales_orders'))
                    ->icon(Heroicon::Banknotes)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('sales_orders_total')
                            ->label(__('common.total'))
                            ->state(fn ($record) => $record->salesOrders()->count())
                            ->numeric()
                            ->weight(FontWeight::Bold)
                            ->size('lg'),
                    ]),

                Section::make(__('resources.customers.infolist.sales_orders'))
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('salesOrders')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3, 'xl' => 4])->schema([
                                    TextEntry::make('reference_code')
                                        ->label(__('resources.sales_orders.fields.reference_code'))
                                        ->fontFamily('mono')
                                        ->weight(FontWeight::Bold)
                                        ->copyable()
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                                    TextEntry::make('warehouse.name')
                                        ->label(__('common.warehouse'))
                                        ->icon(Heroicon::BuildingOffice2)
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                                    TextEntry::make('ordered_at')
                                        ->label(__('resources.sales_orders.fields.ordered_at'))
                                        ->dateTime('M j, Y H:i')
                                        ->placeholder(__('common.not_provided'))
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                                    IconEntry::make('status')
                                        ->label(__('resources.sales_orders.fields.status'))
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                ]),
                            ])
                            ->placeholder(__('common.not_provided')),
                    ]),
            ]),
        ]);
    }
}
