<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_code')
                    ->label('REFERENCE')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('primary'),

                TextColumn::make('customer.name')
                    ->label('CUSTOMER')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('WAREHOUSE')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->color(fn (SalesOrderStatus $state): string => match ($state) {
                        SalesOrderStatus::Draft => 'gray',
                        SalesOrderStatus::Confirmed => 'primary',
                        SalesOrderStatus::PartiallyDispatched => 'warning',
                        SalesOrderStatus::Dispatched => 'info',
                        SalesOrderStatus::Completed => 'success',
                        SalesOrderStatus::Cancelled => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('ordered_by')
                    ->label('ORDERED BY')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('confirmed_by')
                    ->label('CONFIRMED BY')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('dispatched_by')
                    ->label('DISPATCHED BY')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('ordered_at')
                    ->label('ORDERED AT')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('confirmed_at')
                    ->label('CONFIRMED AT')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('dispatched_at')
                    ->label('DISPATCHED AT')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('cancelled_at')
                    ->label('CANCELLED AT')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(SalesOrderStatus::class)
                    ->label('STATUS'),

                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->label('CUSTOMER'),

                SelectFilter::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->label('WAREHOUSE'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge),

                EditAction::make()
                    ->visible(fn (SalesOrder $record): bool => $record->status === SalesOrderStatus::Draft)
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge),

                Action::make('confirmSalesOrder')
                    ->label('CONFIRM')
                    ->icon(\Filament\Support\Icons\Heroicon::CheckCircle)
                    ->color('primary')
                    ->authorize('confirmSalesOrder')
                    ->visible(fn (SalesOrder $record): bool => $record->status === SalesOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        app(\App\Services\SalesService::class)->confirmSalesOrder($record);
                        Notification::make()
                            ->title('Sales order confirmed')
                            ->success()
                            ->send();
                    }),

                Action::make('dispatchSale')
                    ->label('DISPATCH')
                    ->icon(\Filament\Support\Icons\Heroicon::Truck)
                    ->color('success')
                    ->authorize('dispatchSale')
                    ->visible(fn (SalesOrder $record): bool => in_array($record->status, [
                        SalesOrderStatus::Confirmed,
                        SalesOrderStatus::PartiallyDispatched,
                    ]))
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
                    ->schema([
                        \Filament\Forms\Components\Repeater::make('items')
                            ->label('DISPATCH LINES')
                            ->schema([
                                \Filament\Forms\Components\Grid::make(5)->schema([
                                    \Filament\Forms\Components\Select::make('item_id')
                                        ->label('LINE')
                                        ->options(fn (SalesOrder $record) => $record->items->pluck('productVariant.sku', 'id')->toArray())
                                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->productVariant->sku} — {$record->productVariant->name}")
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->columnSpan(2),

                                    \Filament\Forms\Components\TextInput::make('dispatched_base_qty')
                                        ->label('DISPATCHED BASE QTY')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\Select::make('unit_name')
                                        ->label('UNIT')
                                        ->options(fn (SalesOrder $record) => $record->items->pluck('unit_name', 'id')->toArray())
                                        ->required()
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\TextInput::make('unit_ratio')
                                        ->label('RATIO')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\Textarea::make('notes')
                                        ->label('NOTES')
                                        ->columnSpanFull(),
                                ]),
                            ])
                            ->columns(5)
                            ->defaultItems(0)
                            ->addActionLabel('ADD DISPATCH LINE'),
                    ])
                    ->action(function (array $data, SalesOrder $record) {
                        app(\App\Services\SalesService::class)->dispatchSale($record, $data['items']);
                        Notification::make()
                            ->title('Sales order dispatched')
                            ->success()
                            ->send();
                    }),

                Action::make('recordReturn')
                    ->label('RECORD RETURN')
                    ->icon(\Filament\Support\Icons\Heroicon::ArrowUturnLeft)
                    ->color('warning')
                    ->authorize('recordReturn')
                    ->visible(fn (SalesOrder $record): bool => in_array($record->status, [
                        SalesOrderStatus::Dispatched,
                        SalesOrderStatus::Completed,
                    ]))
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
                    ->schema([
                        \Filament\Forms\Components\Repeater::make('items')
                            ->label('RETURN LINES')
                            ->schema([
                                \Filament\Forms\Components\Grid::make(5)->schema([
                                    \Filament\Forms\Components\Select::make('item_id')
                                        ->label('LINE')
                                        ->options(fn (SalesOrder $record) => $record->items->pluck('productVariant.sku', 'id')->toArray())
                                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->productVariant->sku} — {$record->productVariant->name}")
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->columnSpan(2),

                                    \Filament\Forms\Components\TextInput::make('return_base_qty')
                                        ->label('RETURN BASE QTY')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\Select::make('unit_name')
                                        ->label('UNIT')
                                        ->options(fn (SalesOrder $record) => $record->items->pluck('unit_name', 'id')->toArray())
                                        ->required()
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\TextInput::make('unit_ratio')
                                        ->label('RATIO')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\Textarea::make('notes')
                                        ->label('NOTES')
                                        ->columnSpanFull(),
                                ]),
                            ])
                            ->columns(5)
                            ->defaultItems(0)
                            ->addActionLabel('ADD RETURN LINE'),
                    ])
                    ->action(function (array $data, SalesOrder $record) {
                        app(\App\Services\SalesService::class)->recordReturn($record, $data['items']);
                        Notification::make()
                            ->title('Sales return recorded')
                            ->success()
                            ->send();
                    }),

                Action::make('cancelSalesOrder')
                    ->label('CANCEL')
                    ->icon(\Filament\Support\Icons\Heroicon::XCircle)
                    ->color('danger')
                    ->authorize('cancelSalesOrder')
                    ->visible(fn (SalesOrder $record): bool => in_array($record->status, [
                        SalesOrderStatus::Draft,
                        SalesOrderStatus::Confirmed,
                        SalesOrderStatus::PartiallyDispatched,
                    ]))
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        app(\App\Services\SalesService::class)->cancelSalesOrder($record);
                        Notification::make()
                            ->title('Sales order cancelled')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->authorize('delete')
                    ->visible(fn (SalesOrder $record): bool => in_array($record->status, [
                        SalesOrderStatus::Draft,
                        SalesOrderStatus::Cancelled,
                    ])),

                RestoreAction::make()
                    ->authorize('restore'),

                ForceDeleteAction::make()
                    ->authorize('forceDelete')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),

                    RestoreBulkAction::make()
                        ->authorize('restoreAny'),

                    ForceDeleteBulkAction::make()
                        ->authorize('forceDeleteAny'),
                ]),
            ]);
    }
}
