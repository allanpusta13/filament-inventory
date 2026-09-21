<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->searchable()->sortable(),
                TextColumn::make('sku')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('barcode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_unit_name')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currentPrice.cost_price')
                    ->money(config('app.currency'))
                    ->label('COST PRICE'),
                TextColumn::make('currentPrice.sale_price')
                    ->money(config('app.currency'))
                    ->label('SALE PRICE'),
                TextColumn::make('reorder_point')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('CREATED ON')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('LAST MODIFIED')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                SelectFilter::make('product_id')->relationship('product', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(\Filament\Support\Enums\Width::Large),

                Action::make('setCurrentPrice')
                    ->label('SET CURRENT PRICE')
                    ->icon(Heroicon::CurrencyDollar)
                    ->color('primary')
                    ->authorize('update')
                    ->modalWidth(\Filament\Support\Enums\Width::Medium)
                    ->schema([
                        \Filament\Forms\Components\Select::make('price_type')
                            ->label('Price Type')
                            ->options([
                                'cost' => 'Cost Price',
                                'sale' => 'Sale Price',
                            ])
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->step(0.01)
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('effective_from')
                            ->label('Effective From')
                            ->default(now())
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, $record) {
                        $costPrice = $data['price_type'] === 'cost' ? $data['price'] : 0;
                        $salePrice = $data['price_type'] === 'sale' ? $data['price'] : 0;

                        \App\Models\ProductVariantPrice::recordNewPrice(
                            $record,
                            $costPrice,
                            $salePrice,
                            auth()->id(),
                            $data['notes']
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Price updated')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Action::make('editProductFamily')
                    ->label('EDIT PRODUCT FAMILY')
                    ->icon(Heroicon::CubeTransparent)
                    ->color('info')
                    ->authorize('update')
                    ->modalWidth(\Filament\Support\Enums\Width::Large)
                    ->schema([
                        \Filament\Forms\Components\Select::make('product_id')
                            ->label('Product Family')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm(fn ($schema) => $schema->components([
                                \Filament\Forms\Components\TextInput::make('name')->required(),
                                \Filament\Forms\Components\TextInput::make('category')->nullable(),
                            ]))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Variant Name')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('base_unit_name')
                            ->label('Base Unit')
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        $record->update([
                            'product_id' => $data['product_id'],
                            'name' => $data['name'],
                            'base_unit_name' => $data['base_unit_name'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Product family updated')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Action::make('manageUnitConversions')
                    ->label('MANAGE UNIT CONVERSIONS')
                    ->icon(Heroicon::ArrowPath)
                    ->color('warning')
                    ->authorize('update')
                    ->modalWidth(\Filament\Support\Enums\Width::ExtraLarge)
                    ->schema([
                        \Filament\Forms\Components\Repeater::make('unitConversions')
                            ->label('Unit Conversions')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('unit_name')
                                    ->label('Unit Name')
                                    ->required(),
                                \Filament\Forms\Components\TextInput::make('ratio')
                                    ->label('Ratio (to base)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),
                                \Filament\Forms\Components\Toggle::make('is_default')
                                    ->label('Default Purchase Unit'),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add Conversion'),
                    ])
                    ->action(function (array $data, $record) {
                        // Delete existing
                        $record->unitConversions()->delete();
                        // Create new
                        foreach ($data['unitConversions'] as $uc) {
                            $record->unitConversions()->create([
                                'unit_name' => $uc['unit_name'],
                                'base_unit_ratio' => $uc['ratio'],
                                'is_default_purchase' => $uc['is_default'] ?? false,
                                'is_default_transfer' => false,
                            ]);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Unit conversions updated')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Action::make('quickStockAdjustment')
                    ->label('QUICK STOCK ADJUSTMENT')
                    ->icon(Heroicon::ArrowsUpDown)
                    ->color('success')
                    ->authorize('adjustStock')
                    ->modalWidth(\Filament\Support\Enums\Width::Medium)
                    ->schema([
                        \Filament\Forms\Components\Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->options(fn () => \App\Models\Warehouse::pluck('name', 'id')->toArray())
                            ->required()
                            ->searchable()
                            ->preload(),
                        \Filament\Forms\Components\TextInput::make('adjustment_qty')
                            ->label('Adjustment Quantity (Base Units)')
                            ->numeric()
                            ->required(),
                        \Filament\Forms\Components\Select::make('adjustment_type')
                            ->label('Adjustment Type')
                            ->options([
                                'adjustment' => 'General Adjustment',
                                'receive' => 'Receive',
                                'ship' => 'Ship',
                            ])
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, $record) {
                        app(\App\Services\InventoryService::class)->adjustStock(
                            $record->id,
                            $data['warehouse_id'],
                            (int) $data['adjustment_qty'],
                            $data['adjustment_type'],
                            $data['notes'] ?? 'Quick adjustment from product table'
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Stock adjusted')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                DeleteAction::make()
                    ->authorize('delete'),

                RestoreAction::make()
                    ->authorize('restore'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),

                    RestoreBulkAction::make()
                        ->authorize('restoreAny'),
                ]),
            ]);
    }
}
