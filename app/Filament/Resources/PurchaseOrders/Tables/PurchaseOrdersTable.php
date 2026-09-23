<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Support\Filters\AdminReviewFilters;
use App\Models\PurchaseOrder;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
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

                TextColumn::make('supplier.name')
                    ->label(__('SUPPLIER'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label(__('WAREHOUSE'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('STATUS'))
                    ->badge()
                    ->color(fn (PurchaseOrderStatus $state): string => match ($state) {
                        PurchaseOrderStatus::Draft => 'gray',
                        PurchaseOrderStatus::Ordered => 'info',
                        PurchaseOrderStatus::PartiallyReceived => 'warning',
                        PurchaseOrderStatus::Completed => 'success',
                        PurchaseOrderStatus::Cancelled => 'gray',
                    })
                    ->searchable(),

                IconColumn::make('update_cost_price')
                    ->label(__('UPDATE COST'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('ordered_by')
                    ->label(__('ORDERED BY'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('received_by')
                    ->label(__('RECEIVED BY'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('ordered_at')
                    ->label(__('ORDERED AT'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('received_at')
                    ->label(__('RECEIVED AT'))
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
                    ->options(PurchaseOrderStatus::class)
                    ->label(__('STATUS')),

                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('SUPPLIER')),

                SelectFilter::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('WAREHOUSE')),

                TrashedFilter::make(),

                // [Added v11.1] Admin/Auditor-only cross-warehouse review filters.
                // Note the existing warehouse_id SelectFilter above already covers
                // basic warehouse filtering for all users — AdminReviewFilters::warehouse()
                // is intentionally NOT duplicated here for this resource, since a plain
                // SelectFilter on the same column already exists. Only the period
                // filter is added here; see StockMovementsTable / LossLedgersTable
                // below for a resource that needs both because it previously had
                // neither.
                AdminReviewFilters::period('ordered_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAdminReview', PurchaseOrder::class) ?? false),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge),

                EditAction::make()
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Draft)
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge),

                Action::make('orderPurchase')
                    ->label(__('ORDER'))
                    ->icon(\Filament\Support\Icons\Heroicon::PaperAirplane)
                    ->color('primary')
                    ->authorize('orderPurchase')
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record) {
                        app(\App\Services\PurchaseService::class)->orderPurchase($record);
                        Notification::make()
                            ->title(__('Purchase order placed'))
                            ->success()
                            ->send();
                    }),

                Action::make('receivePurchase')
                    ->label(__('RECEIVE'))
                    ->icon(\Filament\Support\Icons\Heroicon::ArrowDownTray)
                    ->color('success')
                    ->authorize('receivePurchase')
                    ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived]))
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
                    ->schema([
                        \Filament\Forms\Components\Repeater::make('items')
                            ->label(__('RECEIPT LINES'))
                            ->schema([
                                \Filament\Schemas\Components\Grid::make(5)->schema([
                                    \Filament\Forms\Components\Select::make('item_id')
                                        ->label(__('LINE'))
                                        ->options(fn (PurchaseOrder $record) => $record->items->pluck('productVariant.sku', 'id')->toArray())
                                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->productVariant->sku} — {$record->productVariant->name}")
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->columnSpan(2),

                                    \Filament\Forms\Components\TextInput::make('received_base_qty')
                                        ->label(__('RECEIVED BASE QTY'))
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\Select::make('unit_name')
                                        ->label(__('UNIT'))
                                        ->options(fn (PurchaseOrder $record) => $record->items->pluck('ordered_unit_name', 'id')->toArray())
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
                            ->addActionLabel(__('ADD RECEIPT LINE')),
                    ])
                    ->action(function (array $data, PurchaseOrder $record) {
                        app(\App\Services\PurchaseService::class)->receivePurchase($record, $data['items']);
                        Notification::make()
                            ->title(__('Purchase received'))
                            ->success()
                            ->send();
                    }),

                Action::make('cancelPurchase')
                    ->label(__('CANCEL'))
                    ->icon(\Filament\Support\Icons\Heroicon::XCircle)
                    ->color('danger')
                    ->authorize('cancelPurchase')
                    ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [
                        PurchaseOrderStatus::Draft,
                        PurchaseOrderStatus::Ordered,
                        PurchaseOrderStatus::PartiallyReceived,
                    ]))
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record) {
                        app(\App\Services\PurchaseService::class)->cancelPurchaseOrder($record);
                        Notification::make()
                            ->title(__('Purchase order cancelled'))
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->authorize('delete')
                    ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [
                        PurchaseOrderStatus::Draft,
                        PurchaseOrderStatus::Cancelled,
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
