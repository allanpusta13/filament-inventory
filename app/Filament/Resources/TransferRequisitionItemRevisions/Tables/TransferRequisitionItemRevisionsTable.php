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
    public static function configure(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('transferRequisitionItem.transferRequisition.reference_code')
                    ->label('REQUISITION REF')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('primary'),

                \Filament\Tables\Columns\TextColumn::make('productVariant.sku')
                    ->label('VARIANT SKU')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('productVariant.name')
                    ->label('VARIANT NAME')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('side')
                    ->label('NEGOTIATION SIDE')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'fulfiller' => 'primary',
                        'requestor' => 'success',
                        default => 'gray',
                    }),

                \Filament\Tables\Columns\TextColumn::make('status')
                    ->label('REVISION STATUS')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'countered' => 'info',
                        default => 'gray',
                    }),

                \Filament\Tables\Columns\TextColumn::make('proposed_unit_name')
                    ->label('UNIT NAME')
                    ->placeholder('—'),

                \Filament\Tables\Columns\TextColumn::make('proposed_qty')
                    ->label('QTY')
                    ->numeric()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('proposed_base_qty')
                    ->label('BASE QTY')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->numeric()
                    ->sortable()
                    ->color('primary'),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('CREATED')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'rejected' => 'Rejected',
                        'countered' => 'Countered',
                    ])
                    ->label('REVISION STATUS'),

                \Filament\Tables\Filters\SelectFilter::make('side')
                    ->options([
                        'fulfiller' => 'Fulfiller',
                        'requestor' => 'Requestor',
                    ])
                    ->label('NEGOTIATION SIDE'),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}