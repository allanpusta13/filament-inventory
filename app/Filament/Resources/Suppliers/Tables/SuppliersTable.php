<?php

declare(strict_types=1);

namespace App\Filament\Resources\Suppliers\Tables;

use App\Models\Supplier;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('NAME')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('primary'),

                TextColumn::make('contact_person')
                    ->label('CONTACT PERSON')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('PHONE')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('EMAIL')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('address')
                    ->label('ADDRESS')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('ACTIVE')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('deleted_at')
                    ->dateTime()
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
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge),

                EditAction::make()
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge),

                DeleteAction::make()
                    ->authorize('delete')
                    ->visible(fn (Supplier $record): bool => $record->trashed()),

                RestoreAction::make()
                    ->authorize('restore'),

                ForceDeleteAction::make()
                    ->authorize('forceDelete')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),

                    RestoreBulkAction::make()
                        ->authorize('restoreAny'),

                    ForceDeleteBulkAction::make()
                        ->authorize('forceDeleteAny'),
                ]),
            ]);
    }
}
