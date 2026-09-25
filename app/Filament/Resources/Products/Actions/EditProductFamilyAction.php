<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Actions;

use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class EditProductFamilyAction
{
    public static function make(): Action
    {
        return Action::make('editProductFamily')
            ->label(__('resources.products.actions.edit_family'))
            ->icon(Heroicon::FolderOpen)
            ->modalWidth(Width::Large)
            ->authorize('update')
            ->fillForm(fn (ProductVariant $record) => [
                'family_name' => $record->product?->name,
                'family_category' => $record->product?->category,
            ])
            ->schema([
                TextInput::make('family_name')
                    ->label(__('resources.products.fields.family_name'))
                    ->prefixIcon(Heroicon::Identification)
                    ->columnSpanFull()
                    ->required()
                    ->maxLength(255),

                TextInput::make('family_category')
                    ->label(__('resources.products.fields.family_category'))
                    ->prefixIcon(Heroicon::Tag)
                    ->columnSpanFull()
                    ->maxLength(255),
            ])
            ->action(function (array $data, ProductVariant $record) {
                $product = $record->product;

                if (! $product) {
                    Notification::make()
                        ->title(__('resources.products.notifications.family_missing'))
                        ->danger()
                        ->send();

                    return;
                }

                $product->update([
                    'name' => $data['family_name'],
                    'category' => $data['family_category'] ?: null,
                ]);

                Notification::make()
                    ->title(__('resources.products.notifications.family_updated'))
                    ->success()
                    ->send();
            });
    }
}
