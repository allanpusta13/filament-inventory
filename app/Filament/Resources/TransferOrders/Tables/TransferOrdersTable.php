<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders\Tables;

use App\Enums\UserRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class TransferOrdersTable
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
                    $q->whereIn('sender_branch_id', $warehouseIds)
                        ->orWhereIn('receiver_branch_id', $warehouseIds);
                });
            })
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with([
                'sender',
                'receiver',
            ]))
            ->columns([
                TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sender.name')
                    ->label('From')
                    ->sortable(),
                TextColumn::make('receiver.name')
                    ->label('To')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('driver_name')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dispatched_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('received_at')
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
                        'received' => 'Received',
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
