<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransit;

use App\Filament\Resources\InTransit\Pages\ListInTransits;
use App\Filament\Resources\InTransit\Pages\ViewInTransit;
use App\Models\InTransit;
use App\Models\TransferRequisition;
use App\Services\InventoryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class InTransitResource extends Resource
{
    protected static ?string $model = InTransit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static bool $canCreate = false;

    protected static bool $canEdit = false;

    protected static bool $canDelete = false;

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requisition.reference_code')
                    ->label('Requisition Code')
                    ->searchable(),
                TextColumn::make('requisition.fromWarehouse.name')
                    ->label('From Warehouse')
                    ->searchable(),
                TextColumn::make('requisition.toWarehouse.name')
                    ->label('To Warehouse')
                    ->searchable(),
                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('dispatched_base_qty')
                    ->label('Dispatched Qty')
                    ->numeric()
                    ->summarize(Sum::make()->label('Total In Transit')),
                TextColumn::make('dispatched_at')
                    ->label('Dispatched At')
                    ->dateTime(),
                TextColumn::make('dispatched_at')
                    ->label('Elapsed Duration')
                    ->formatStateUsing(fn ($state) => now()->diffInHours($state).' hours ago')
                    ->color('warning'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('confirmReceipt')
                    ->label('Confirm Receipt')
                    ->icon('heroicon-o-clipboard-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalWidth('2xl')
                    ->form([
                        Placeholder::make('info')
                            ->label('Confirm Receipt of Items')
                            ->content('Please confirm that you have received all items listed below. This will update inventory levels and create loss ledger entries for any discrepancies.'),
                        Repeater::make('items')
                            ->label('Items to Receive')
                            ->schema([
                                TextInput::make('item_id')
                                    ->dehydrated()
                                    ->readOnly()
                                    ->hidden(),
                                TextInput::make('variant_sku')
                                    ->label('Variant')
                                    ->readOnly(),
                                TextInput::make('expected_qty')
                                    ->label('Expected Qty')
                                    ->readOnly()
                                    ->numeric(),
                                TextInput::make('good_qty')
                                    ->label('Good Qty')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->default(fn (array $state): int => (int) ($state['expected_qty'] ?? 0))
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, $set, $get) => $this->validateReceiptQty($set, $get, (int) ($get['expected_qty'] ?? 0))),
                                TextInput::make('damaged_qty')
                                    ->label('Damaged Qty')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, $set, $get) => $this->validateReceiptQty($set, $get, (int) ($get['expected_qty'] ?? 0))),
                                Placeholder::make('lost_qty_display')
                                    ->label('Lost Qty (Calculated)')
                                    ->content(fn (array $state): string => number_format(max(0, (int) ($get['expected_qty'] ?? 0) - ((int) ($state['good_qty'] ?? 0) + (int) ($state['damaged_qty'] ?? 0))))),
                                Select::make('loss_category')
                                    ->label('Loss Reason')
                                    ->options([
                                        'Damaged in Transit' => 'Damaged in Transit',
                                        'Short Shipment' => 'Short Shipment',
                                        'Spoiled' => 'Spoiled',
                                        'Transit Variance' => 'Transit Variance',
                                    ])
                                    ->required(fn (array $state) => ((int) ($state['lost_qty'] ?? 0) > 0 || (int) ($state['damaged_qty'] ?? 0) > 0))
                                    ->visible(fn (array $state) => ((int) ($state['lost_qty'] ?? 0) > 0 || (int) ($state['damaged_qty'] ?? 0) > 0))
                            ])
                            ->columns(2)
                            ->default(fn (InTransit $inTransit) => $inTransit->requisition->items->map(function ($item) {
                                return [
                                    'item_id' => $item->id,
                                    'variant_sku' => $item->variant->sku . ' - ' . $item->variant->name,
                                    'expected_qty' => $item->shipped_base_qty,
                                ];
                            })->toArray())
                    ])
                    ->action(function (array $data, InTransit $inTransit): void {
                        // Transform data for InventoryService::scanToReceive()
                        $receivedData = [];
                        foreach ($data['items'] as $item) {
                            $receivedData[$item['item_id']] = [
                                'good_qty' => (int) $item['good_qty'],
                                'damaged_qty' => (int) $item['damaged_qty'],
                                'loss_category' => $item['loss_category'] ?? 'Transit Variance',
                            ];
                        }

                        // Execute the receiving transaction
                        app(InventoryService::class)->scanToReceive(
                            $inTransit->requisition_id,
                            $receivedData,
                            auth()->id(),
                            auth()->user()
                        );

                        Notification::make()
                            ->title('Receipt Confirmed')
                            ->body("InTransit record #{$inTransit->id} has been processed and items received.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                // No bulk actions for read-only
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();

                return $query
                    ->with(['requisition.fromWarehouse', 'requisition.toWarehouse', 'variant'])
                    ->where('status', 'in_transit')
                    ->when(! $user->isAdmin() && ! $user->isAuditor(), function ($query) use ($user) {
                        // Restrict to shipments involving the worker's warehouse
                        $warehouseIds = $user->warehouses->pluck('id');
                        $query->whereHas('requisition', function ($q) use ($warehouseIds) {
                            $q->whereIn('from_warehouse_id', $warehouseIds)
                                ->orWhereIn('to_warehouse_id', $warehouseIds);
                        });
                    });
            });
    }

    /**
     * Validate that received quantities don't exceed expected quantities.
     */
    public function validateReceiptQty($set, $get, int $expectedQty): void
    {
        $goodQty = (int) ($get['good_qty'] ?? 0);
        $damagedQty = (int) ($get['damaged_qty'] ?? 0);
        $totalReceived = $goodQty + $damagedQty;

        if ($totalReceived > $expectedQty) {
            $set('good_qty', $expectedQty);
            $set('damaged_qty', 0);
            
            Notification::make()
                ->title('Invalid Entry')
                ->body('Combined good and damaged quantities cannot exceed the expected quantity.')
                ->warning()
                ->send();
        } else {
            $set('lost_qty', $expectedQty - $totalReceived);
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['requisition.fromWarehouse', 'requisition.toWarehouse', 'variant']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInTransits::route('/'),
            'view' => ViewInTransit::route('/view/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::getModel()::count();
    }
}
