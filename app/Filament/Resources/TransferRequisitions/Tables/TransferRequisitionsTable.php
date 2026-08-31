<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Tables;

use App\Enums\UserRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class TransferRequisitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query): void {
                /** @var \App\Models\User|null $user */
                $user = auth()->user();

                if (! $user) {
                    return;
                }

                if (in_array($user->role, [UserRole::Admin, UserRole::Auditor], true)) {
                    return;
                }

                $warehouseIds = $user->warehouses()->pluck('warehouses.id')->toArray();

                $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($warehouseIds): void {
                    $q->whereIn('from_warehouse_id', $warehouseIds)
                        ->orWhereIn('to_warehouse_id', $warehouseIds);
                });
            })
            ->columns([
                TextColumn::make('reference_code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('fromWarehouse.name')
                    ->label('From')
                    ->sortable(),
                TextColumn::make('toWarehouse.name')
                    ->label('To')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'requested' => 'info',
                        'under_review_fulfiller', 'under_review_requestor' => 'warning',
                        'confirmed' => 'success',
                        'dispatched' => 'primary',
                        'partially_received' => 'warning',
                        'completed' => 'success',
                        'closed_with_loss' => 'danger',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->sortable(),
                TextColumn::make('requested_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'requested' => 'Requested',
                        'under_review_fulfiller' => 'Under Review (Fulfiller)',
                        'under_review_requestor' => 'Under Review (Requestor)',
                        'confirmed' => 'Confirmed',
                        'dispatched' => 'Dispatched',
                        'partially_received' => 'Partially Received',
                        'completed' => 'Completed',
                        'closed_with_loss' => 'Closed with Loss',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
