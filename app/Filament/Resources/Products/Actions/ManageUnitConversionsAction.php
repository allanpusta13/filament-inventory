<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ManageUnitConversionsAction
{
    public static function make(): Action
    {
        return Action::make('manageUnitConversions')
            ->label(__('resources.products.actions.manage_units'))
            ->icon(Heroicon::Scale)
            ->modalWidth(Width::SevenExtraLarge)
            ->authorize('update')
            ->fillForm(fn ($record) => [
                'unitConversions' => $record->unitConversions
                    ->map->only(['unit_name', 'base_unit_ratio', 'is_default_purchase', 'is_default_transfer'])
                    ->toArray(),
            ])
            ->schema([
                Repeater::make('unitConversions')
                    ->schema([
                        TextInput::make('unit_name')
                            ->label(__('resources.products.fields.unit_name'))
                            ->required()
                            ->disabled(fn (Get $get, $record) => $get('unit_name') === $record->base_unit_name),

                        TextInput::make('base_unit_ratio')
                            ->label(__('resources.products.fields.base_unit_ratio'))
                            ->numeric()
                            ->required()
                            ->disabled(fn (Get $get, $record) => $get('unit_name') === $record->base_unit_name),

                        Toggle::make('is_default_purchase')
                            ->label(__('resources.products.fields.is_default_purchase')),

                        Toggle::make('is_default_transfer')
                            ->label(__('resources.products.fields.is_default_transfer')),
                    ])
                    ->columns(4)
                    ->deletable(fn ($record, array $item) => ($item['unit_name'] ?? null) !== $record->base_unit_name),
            ])
            ->action(function (array $data, $record) {
                $baseName = $record->base_unit_name;
                $incoming = collect($data['unitConversions'] ?? []);

                $record->unitConversions()
                    ->where('unit_name', '!=', $baseName)
                    ->delete();

                foreach ($incoming as $conv) {
                    if (($conv['unit_name'] ?? null) === $baseName) {
                        continue;
                    }

                    $record->unitConversions()->create($conv);
                }
            });
    }
}
