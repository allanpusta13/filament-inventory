<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitionItemRevisions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransferRequisitionItemRevisionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transferRequisitionItem.transferRequisition.reference_code')
                    ->label('REQUISITION REF')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('primary'),

                TextColumn::make('productVariant.sku')
                    ->label('VARIANT SKU')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('productVariant.name')
                    ->label('VARIANT NAME')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('side')
                    ->label('NEGOTIATION SIDE')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'fulfiller' => 'primary',
                        'requestor' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('REVISION STATUS')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'countered' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('proposed_unit_name')
                    ->label('UNIT NAME')
                    ->placeholder('—'),

                TextColumn::make('proposed_qty')
                    ->label('QTY')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('proposed_base_qty')
                    ->label('BASE QTY')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->numeric()
                    ->sortable()
                    ->color('primary'),

                TextColumn::make('created_at')
                    ->label('CREATED')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'rejected' => 'Rejected',
                        'countered' => 'Countered',
                    ])
                    ->label('REVISION STATUS'),

                SelectFilter::make('side')
                    ->options([
                        'fulfiller' => 'Fulfiller',
                        'requestor' => 'Requestor',
                    ])
                    ->label('NEGOTIATION SIDE'),
            ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
