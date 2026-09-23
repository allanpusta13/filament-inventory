<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Enums\SalesOrderStatus;
use App\Filament\Support\Filters\AdminReviewFilters;
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
                    ->label(__('REFERENCE'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('primary'),

                TextColumn::make('customer.name')
                    ->label(__('CUSTOMER'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label(__('WAREHOUSE'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('STATUS'))
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
                    ->label(__('ORDERED BY'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('confirmed_by')
                    ->label(__('CONFIRMED BY'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('dispatched_by')
                    ->label(__('DISPATCHED BY'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('ordered_at')
                    ->label(__('ORDERED AT'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('confirmed_at')
                    ->label(__('CONFIRMED AT'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('dispatched_at')
                    ->label(__('DISPATCHED AT'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('cancelled_at')
                    ->label(__('CANCELLED AT'))
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
                    ->label(__('STATUS')),

                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('CUSTOMER')),

                SelectFilter::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('WAREHOUSE')),

                TrashedFilter::make(),

                // [Added v11.1] Admin/Auditor-only cross-warehouse review period filter.
                AdminReviewFilters::period('confirmed_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAdminReview', SalesOrder::class) ?? false),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge),

                EditAction::make()
                    ->visible(fn (SalesOrder $record): bool => $record->status === SalesOrderStatus::Draft)
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge),

                Action::make('confirmSalesOrder')
                    ->label(__('CONFIRM'))
                    ->icon(\Filament\Support\Icons\Heroicon::CheckCircle)
                    ->color('primary')
                    ->authorize('confirmSalesOrder')
                    ->visible(fn (SalesOrder $record): bool => $record->status === SalesOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        app(\App\Services\SalesService::class)->confirmSalesOrder($record);
                        Notification::make()
                            ->title(__('Sales order confirmed'))
                            ->success()
                            ->send();
                    }),

                Action::make('dispatchSale')
                    ->label(__('DISPATCH'))
                    ->icon(\Filament\Support\Icons\Heroicon::Truck)
                    ->color('success')
                    ->authorize('dispatchSale')
                    ->visible(fn (SalesOrder $record): bool => in_array($record->status, [
                        SalesOrderStatus::Confirmed,
                        SalesOrderStatus::PartiallyDispatched,
                    ]))
                    ->modalWidth(\Filament\Support\Enums\Width::FourExtraLarge)
                    ->schema(function (SalesOrder $record) {
                        $variantIds = $record->items->pluck('product_variant_id')->all();
                        $availableByVariant = \App\Models\ProductVariant::batchAvailableQuantity($variantIds, $record->warehouse_id);

                        return collect($record->items)
                            ->map(function ($item) use ($availableByVariant) {
                                $available = $availableByVariant[$item->product_variant_id] ?? 0;
                                $safeMax = min($item->outstandingBaseQty(), max(0, $available));

                                return \Filament\Forms\Components\TextInput::make("dispatch.{$item->id}")
                                    ->label(__(':sku — outstanding :outstanding :unit (available: :available)', [
                                        'sku' => $item->productVariant->sku,
                                        'outstanding' => $item->outstandingBaseQty(),
                                        'unit' => $item->unit_name,
                                        'available' => $available,
                                    ]))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue($safeMax)
                                    ->default($safeMax)
                                    ->helperText($available < $item->outstandingBaseQty()
                                        ? __('Insufficient stock for full dispatch — partial dispatch only.')
                                        : null);
                            })
                            ->all();
                    })
                    ->action(function (array $data, SalesOrder $record) {
                        $dispatch = collect($data['dispatch'] ?? [])
                            ->filter(fn ($qty) => (int) $qty > 0)
                            ->mapWithKeys(fn ($qty, $itemId) => [(int) $itemId => (int) $qty])
                            ->all();

                        app(\App\Services\SalesService::class)->dispatchSale($record->id, $dispatch);

                        Notification::make()
                            ->title(__('Sales order dispatched'))
                            ->success()
                            ->send();
                    }),

                Action::make('recordReturn')
                    ->label(__('RECORD RETURN'))
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
                            ->label(__('RETURN LINES'))
                            ->schema([
                                \Filament\Schemas\Components\Grid::make(5)->schema([
                                    \Filament\Forms\Components\Select::make('item_id')
                                        ->label(__('LINE'))
                                        ->options(fn (SalesOrder $record) => $record->items->pluck('productVariant.sku', 'id')->toArray())
                                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->productVariant->sku} — {$record->productVariant->name}")
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->columnSpan(2),

                                    \Filament\Forms\Components\TextInput::make('return_base_qty')
                                        ->label(__('RETURN BASE QTY'))
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\Select::make('unit_name')
                                        ->label(__('UNIT'))
                                        ->options(fn (SalesOrder $record) => $record->items->pluck('unit_name', 'id')->toArray())
                                        ->required()
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\TextInput::make('unit_ratio')
                                        ->label(__('RATIO'))
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\Textarea::make('notes')
                                        ->label(__('NOTES'))
                                        ->columnSpanFull(),
                                ]),
                            ])
                            ->columns(5)
                            ->defaultItems(0)
                            ->addActionLabel(__('ADD RETURN LINE')),
                    ])
                    ->action(function (array $data, SalesOrder $record) {
                        app(\App\Services\SalesService::class)->recordReturn($record, $data['items']);
                        Notification::make()
                            ->title(__('Sales return recorded'))
                            ->success()
                            ->send();
                    }),

                Action::make('cancelSalesOrder')
                    ->label(__('CANCEL'))
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
                            ->title(__('Sales order cancelled'))
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
                    ->authorize('forceDelete'),
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
