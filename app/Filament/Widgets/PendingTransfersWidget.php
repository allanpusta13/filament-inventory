<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Models\TransferRequisition;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class PendingTransfersWidget extends BaseWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Pending Transfers';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $isAdmin = $user?->isAdmin() ?? false;

        return $table
            ->query(function () use ($isAdmin, $user) {
                $query = TransferRequisition::whereIn('status', ['requested', 'under_review_fulfiller', 'under_review_requestor']);

                if (! $isAdmin) {
                    $warehouseIds = $user->warehouses()->pluck('warehouses.id')->toArray();
                    $query->where(function ($q) use ($warehouseIds): void {
                        $q->whereIn('from_warehouse_id', $warehouseIds)
                            ->orWhereIn('to_warehouse_id', $warehouseIds);
                    });
                }

                return $query->latest('requested_at');
            })
            ->columns([
                Tables\Columns\TextColumn::make('reference_code')
                    ->label('Reference')
                    ->sortable()
                    ->url(fn (TransferRequisition $record): string => TransferRequisitionResource::getUrl('view', ['record' => $record->id])),
                Tables\Columns\TextColumn::make('fromWarehouse.code')
                    ->label('From')
                    ->sortable(),
                Tables\Columns\TextColumn::make('toWarehouse.code')
                    ->label('To')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'requested' => 'warning',
                        'under_review_fulfiller' => 'info',
                        'under_review_requestor' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('requested_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordUrl(fn (TransferRequisition $record): string => TransferRequisitionResource::getUrl('view', ['record' => $record->id]))
            ->paginated([5]);
    }
}
