<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('OPERATOR NAME')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('EMAIL ADDRESS')
                    ->icon(Heroicon::Envelope)
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->label('SYSTEM ROLE')
                    ->badge(), // Automatically calls getLabel(), getColor(), and getIcon() on UserRole Enum

                TextColumn::make('warehouses.name')
                    ->label('AUTHORIZED BRANCHES')
                    ->badge()
                    ->color('gray')
                    ->placeholder('No Branch Assigned'),

                TextColumn::make('created_at')
                    ->label('REGISTERED DATE')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('SYSTEM ROLE')
                    ->options(UserRole::class),
            ])
            ->actions([
                ViewAction::make()
                    ->slideOver()
                    ->icon(Heroicon::Eye)
                    ->closeModalByClickingAway(false),

                EditAction::make()
                    ->slideOver()
                    ->icon(Heroicon::PencilSquare)
                    ->closeModalByClickingAway(false),

                DeleteAction::make()
                    ->slideOver()
                    ->icon(Heroicon::Trash)
                    ->closeModalByClickingAway(false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
