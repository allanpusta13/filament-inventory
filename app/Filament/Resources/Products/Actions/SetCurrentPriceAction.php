<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Actions;

use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * §7A.4.1 canonical contract.
 *
 * No-op guard: identical cost + sale price produces no new row and no
 * notification. Otherwise: rotate the current row inside a transaction
 * under a variant lock, insert a new current row, notify.
 */
class SetCurrentPriceAction
{
    public static function make(): Action
    {
        return Action::make('setCurrentPrice')
            ->label(__('resources.products.actions.set_current_price'))
            ->modalHeading(__('resources.products.actions.set_current_price_heading'))
            ->modalDescription(__('resources.products.actions.set_current_price_description'))
            ->icon(Heroicon::CurrencyDollar)
            ->color('primary')
            ->modalWidth(Width::Large)
            ->authorize('update')
            ->fillForm(fn (ProductVariant $record) => [
                'cost_price' => $record->currentPrice?->cost_price ?? '0.0000',
                'sale_price' => $record->currentPrice?->sale_price ?? '0.0000',
                'notes' => null,
            ])
            ->schema([
                TextInput::make('cost_price')
                    ->label(__('resources.products.fields.cost_price'))
                    ->prefixIcon(Heroicon::CurrencyDollar)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->numeric()
                    ->step(0.0001)
                    ->minValue(0)
                    ->required(),

                TextInput::make('sale_price')
                    ->label(__('resources.products.fields.sale_price'))
                    ->prefixIcon(Heroicon::CurrencyDollar)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->numeric()
                    ->step(0.0001)
                    ->minValue(0)
                    ->required(),

                TextInput::make('notes')
                    ->label(__('resources.products.fields.price_change_notes'))
                    ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                    ->columnSpanFull()
                    ->maxLength(500),
            ])
            ->action(function (array $data, ProductVariant $record) {
                $persisted = false;

                DB::transaction(function () use ($data, $record, &$persisted) {
                    $variant = ProductVariant::lockForUpdate()->findOrFail($record->id);
                    $current = $variant->currentPrice;

                    if ($current
                        && bccomp((string) $current->cost_price, (string) $data['cost_price'], 4) === 0
                        && bccomp((string) $current->sale_price, (string) $data['sale_price'], 4) === 0) {
                        return;
                    }

                    if ($current) {
                        $current->update(['is_current' => false]);
                    }

                    ProductVariantPrice::create([
                        'product_variant_id' => $variant->id,
                        'cost_price' => $data['cost_price'],
                        'sale_price' => $data['sale_price'],
                        'effective_from' => now(),
                        'is_current' => true,
                        'set_by' => auth()->id(),
                        'notes' => $data['notes'] ?? null,
                    ]);

                    $persisted = true;
                });

                if (! $persisted) {
                    return;
                }

                Notification::make()
                    ->title(__('resources.products.notifications.price_updated'))
                    ->success()
                    ->send();
            })
            ->successNotificationTitle(null);
    }
}
