<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Actions;

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * §7A.4.4 canonical contract.
 *
 * Base-unit self-conversion row (F19) is undeletable via the UI: the
 * repeater's `deleteAction()` hides the delete button on the base row,
 * and the `->action()` re-check skips the base row when persisting.
 * Both guards are required — the UI one is a hint, the action one is
 * the authoritative enforcement.
 */
class ManageUnitConversionsAction
{
    public static function make(): Action
    {
        return Action::make('manageUnitConversions')
            ->label(__('resources.products.actions.manage_units'))
            ->modalHeading(__('resources.products.actions.manage_units_heading'))
            ->modalDescription(__('resources.products.actions.manage_units_description'))
            ->icon(Heroicon::Scale)
            ->modalWidth(Width::SevenExtraLarge)
            ->authorize('update')
            ->fillForm(fn (ProductVariant $record) => [
                'unitConversions' => $record->unitConversions
                    ->map->only(['unit_name', 'base_unit_ratio', 'is_default_purchase', 'is_default_transfer'])
                    ->toArray(),
            ])
            ->schema(fn (ProductVariant $record) => [
                Repeater::make('unitConversions')
                    ->schema([
                        TextInput::make('unit_name')
                            ->label(__('resources.products.fields.unit_name'))
                            ->required()
                            ->disabled(fn (Get $get): bool => $get('unit_name') === $record->base_unit_name),

                        TextInput::make('base_unit_ratio')
                            ->label(__('resources.products.fields.base_unit_ratio'))
                            ->numeric()
                            ->required()
                            ->disabled(fn (Get $get): bool => $get('unit_name') === $record->base_unit_name),

                        Toggle::make('is_default_purchase')
                            ->label(__('resources.products.fields.is_default_purchase')),

                        Toggle::make('is_default_transfer')
                            ->label(__('resources.products.fields.is_default_transfer')),
                    ])
                    ->columns(4)
                    ->deleteAction(
                        fn (Action $action) => $action->visible(
                            fn (Get $get): bool => $get('unit_name') !== $record->base_unit_name
                        ),
                    ),
            ])
            ->action(function (array $data, ProductVariant $record) {
                $baseName = $record->base_unit_name;
                $incoming = collect($data['unitConversions'] ?? []);

                // Authoritative guard: base-unit self-conversion row
                // (F19) is never removed.
                $record->unitConversions()->with('productVariant')->get()
                    ->reject(fn (ProductVariantUnitConversion $conversion): bool => $conversion->isBaseUnitRow())
                    ->each(fn (ProductVariantUnitConversion $conversion) => $conversion->delete());

                foreach ($incoming as $conv) {
                    if (($conv['unit_name'] ?? null) === $baseName) {
                        continue;
                    }

                    $record->unitConversions()->create(
                        collect($conv)->only([
                            'unit_name', 'base_unit_ratio',
                            'is_default_purchase', 'is_default_transfer',
                        ])->toArray()
                    );
                }
            });
    }
}
