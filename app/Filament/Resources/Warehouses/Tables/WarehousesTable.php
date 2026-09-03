<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Tables;

use App\Models\Warehouse;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('location')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
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
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('adjustStock')
                    ->label('Adjust Stock Level')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->slideOver()
                    ->form([
                        Select::make('variant_id')
                            ->label('Select Product Variant')
                            ->options(\App\Models\ProductVariant::pluck('sku', 'id'))
                            ->required()
                            ->searchable(),
                        TextInput::make('adjustment_quantity')
                            ->label('Adjustment delta (Base Units)')
                            ->helperText('Use positive integer for gains, negative integer for losses.')
                            ->integer()
                            ->required(),
                        Textarea::make('audit_reason')
                            ->label('Mandatory Audit Note')
                            ->placeholder('Enter detailed reason for manual override (minimum 15 characters)...')
                            ->required()
                            ->minLength(15)
                            ->rule(function () {
                                return function (string $attribute, $value, $fail) {
                                    if (preg_match('/^(.)\1+$/', mb_trim($value)) || in_array(mb_strtolower(mb_trim($value)), ['manual override', 'stock adjustment', 'test notes', 'temporary adjustment'])) {
                                        $fail('The audit reason must contain a genuine, non-repetitive descriptive explanation.');
                                    }
                                };
                            }),
                    ])
                    ->action(function (Warehouse $record, array $data, InventoryService $service) {
                        $service->recordMovement(
                            variantId: $data['variant_id'],
                            warehouseId: $record->id,
                            type: 'adjustment',
                            baseQuantity: $data['adjustment_quantity'],
                            unitName: 'Base Unit',
                            unitRatio: 1,
                            referenceType: 'ManualAdjustment',
                            referenceId: $record->id,
                            referenceCode: 'ADJ-'.now()->format('Ymd-His'),
                            relatedMovementId: null
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
