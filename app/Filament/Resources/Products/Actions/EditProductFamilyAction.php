<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Actions;

use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * §7A.4.2 canonical contract.
 *
 * Mutates the parent `Product` — so the `ProductPolicy::update` ability
 * governs the authorize check, not `ProductVariantPolicy::update`.
 */
class EditProductFamilyAction
{
    public static function make(): Action
    {
        return Action::make('editProductFamily')
            ->label(__('resources.products.actions.edit_family'))
            ->modalHeading(__('resources.products.actions.edit_family_heading'))
            ->modalDescription(__('resources.products.actions.edit_family_description'))
            ->icon(Heroicon::FolderOpen)
            ->modalWidth(Width::Large)
            ->authorize(function (ProductVariant $record): bool {
                $product = $record->product;

                return $product
                    ? auth()->user()->can('update', $product)
                    : auth()->user()->can('update', $record);
            })
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

                \Illuminate\Support\Facades\Gate::authorize('update', $product);

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
