<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgers\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LossLedgersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transferRequisition.reference_code')
                    ->label('REQUISITION REF')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('primary'),

                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('productVariant.name')
                    ->label('VARIANT NAME')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('WAREHOUSE')
                    ->sortable(),

                TextColumn::make('loss_category')
                    ->label('LOSS CATEGORY')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'shortfall' => 'danger',
                        'damage' => 'warning',
                        'spoilage' => 'gray',
                        'theft' => 'danger',
                        'other' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('lost_base_qty')
                    ->label('LOST (BASE)')
                    ->numeric()
                    ->sortable()
                    ->color('danger'),

                TextColumn::make('damaged_base_qty')
                    ->label('DAMAGED (BASE)')
                    ->numeric()
                    ->sortable()
                    ->color('warning'),

                TextColumn::make('total_financial_loss')
                    ->label('TOTAL FINANCIAL LOSS')
                    ->money(config('app.currency'))
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('danger'),

                TextColumn::make('recorded_at')
                    ->label('RECORDED AT')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('recordedBy.name')
                    ->label('RECORDED BY')
                    ->placeholder('Unknown'),
            ])
            ->filters([
                SelectFilter::make('loss_category')
                    ->options([
                        'shortfall' => 'Shortfall',
                        'damage' => 'Damage',
                        'spoilage' => 'Spoilage',
                        'theft' => 'Theft',
                        'other' => 'Other',
                    ])
                    ->label('LOSS CATEGORY'),

                SelectFilter::make('warehouse_id')
                    ->label('WAREHOUSE')
                    ->relationship('warehouse', 'name'),
            ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make()
                    ->authorize('delete'),
                Action::make('recordLoss')
                    ->label('RECORD LOSS')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->color('danger')
                    ->authorize('recordLoss')
                    ->modalWidth(\Filament\Support\Enums\Width::Large)
                    ->schema([
                        \Filament\Forms\Components\Select::make('product_variant_id')
                            ->label('Product Variant')
                            ->relationship('productVariant', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('loss_category')
                            ->label('Loss Category')
                            ->required()
                            ->datalist(['shortfall', 'damage', 'spoilage', 'theft', 'other']),
                        \Filament\Forms\Components\TextInput::make('lost_base_qty')
                            ->label('Lost Quantity (Base)')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        \Filament\Forms\Components\TextInput::make('damaged_base_qty')
                            ->label('Damaged Quantity (Base)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        \Filament\Forms\Components\TextInput::make('total_financial_loss')
                            ->label('Total Financial Loss')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, $record) {
                        $record->lossLedgers()->create([
                            'product_variant_id' => $data['product_variant_id'],
                            'loss_category' => $data['loss_category'],
                            'lost_base_qty' => $data['lost_base_qty'],
                            'damaged_base_qty' => $data['damaged_base_qty'],
                            'total_financial_loss' => $data['total_financial_loss'],
                            'notes' => $data['notes'],
                            'recorded_by' => auth()->id(),
                            'recorded_at' => now(),
                        ]);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
