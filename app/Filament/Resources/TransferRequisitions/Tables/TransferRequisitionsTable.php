<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransferRequisitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_code')
                    ->label('TRANSFER CODE')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('primary'),

                TextColumn::make('fromWarehouse.name')
                    ->label('ORIGIN SITE')
                    ->sortable(),

                TextColumn::make('toWarehouse.name')
                    ->label('RECEIVING SITE')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('OPERATIONAL STATUS')
                    ->badge(),

                TextColumn::make('requested_at')
                    ->label('SUBMITTED ON')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->recordClasses(fn ($record) => match ($record->status) {
                'under_review_fulfiller', 'under_review_requestor' => 'hover:bg-amber-50/40 dark:hover:bg-amber-950/20 transition-colors',
                default => 'hover:bg-zinc-50 dark:hover:bg-zinc-900/40 transition-colors',
            })
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'requested' => 'Requested',
                        'under_review_fulfiller' => 'Review (Fulfiller)',
                        'under_review_requestor' => 'Review (Requestor)',
                        'confirmed' => 'Confirmed',
                        'dispatched' => 'In-Transit (Dispatched)',
                        'completed' => 'Completed',
                        'closed_with_loss' => 'Closed with Loss',
                    ]),

                SelectFilter::make('from_warehouse_id')
                    ->label('ORIGIN WAREHOUSE')
                    ->relationship('fromWarehouse', 'name'),
            ])
            ->actions([
                ViewAction::make()

                    ->icon(Heroicon::Eye)
                    ->closeModalByClickingAway(false),

                EditAction::make()

                    ->icon(Heroicon::PencilSquare)
                    ->visible(fn ($record) => in_array($record->status, ['draft', 'under_review_requestor']))
                    ->closeModalByClickingAway(false),
            ]);
    }
}
