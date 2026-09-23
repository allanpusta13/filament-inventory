<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
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
                    ->label('REFERENCE')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('primary'),

                TextColumn::make('supplier.name')
                    ->label('SUPPLIER')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('WAREHOUSE')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('STATUS')
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
                    ->label('UPDATE COST')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('ordered_by')
                    ->label('ORDERED BY')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('received_by')
                    ->label('RECEIVED BY')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('ordered_at')
                    ->label('ORDERED AT')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('received_at')
                    ->label('RECEIVED AT')
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
                    ->options(PurchaseOrderStatus::class)
                    ->label('STATUS'),

                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->label('SUPPLIER'),

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
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Draft)
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge),

                Action::make('orderPurchase')
                    ->label('ORDER')
                    ->icon(\Filament\Support\Icons\Heroicon::PaperAirplane)
                    ->color('primary')
                    ->authorize('orderPurchase')
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record) {
                        app(\App\Services\PurchaseService::class)->orderPurchase($record);
                        Notification::make()
                            ->title('Purchase order placed')
                            ->success()
                            ->send();
                    }),

                Action::make('receivePurchase')
                    ->label('RECEIVE')
                    ->icon(\Filament\Support\Icons\Heroicon::ArrowDownTray)
                    ->color('success')
                    ->authorize('receivePurchase')
                    ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived]))
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
                    ->schema([
                        \Filament\Forms\Components\Repeater::make('items')
                            ->label('RECEIPT LINES')
                            ->schema([
                                \Filament\Forms\Components\Grid::make(5)->schema([
                                    \Filament\Forms\Components\Select::make('item_id')
                                        ->label('LINE')
                                        ->options(fn (PurchaseOrder $record) => $record->items->pluck('productVariant.sku', 'id')->toArray())
                                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->productVariant->sku} — {$record->productVariant->name}")
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->columnSpan(2),

                                    \Filament\Forms\Components\TextInput::make('received_base_qty')
                                        ->label('RECEIVED BASE QTY')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->columnSpan(1),

                                    \Filament\Forms\Components\Select::make('unit_name')
                                        ->label('UNIT')
                                        ->options(fn (PurchaseOrder $record) => $record->items->pluck('ordered_unit_name', 'id')->toArray())
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
                            ->addActionLabel('ADD RECEIPT LINE'),
                    ])
                    ->action(function (array $data, PurchaseOrder $record) {
                        app(\App\Services\PurchaseService::class)->receivePurchase($record, $data['items']);
                        Notification::make()
                            ->title('Purchase received')
                            ->success()
                            ->send();
                    }),

                Action::make('cancelPurchase')
                    ->label('CANCEL')
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
                            ->title('Purchase order cancelled')
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
