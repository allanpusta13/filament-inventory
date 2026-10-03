<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Actions;

use App\Models\ProductVariant;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * §7A.4.3 canonical contract.
 *
 * Operational ability — `ProductVariantPolicy::adjustStock`. The
 * warehouse select is scoped to assigned warehouses for non-admins
 * (auditors are read-only and denied by the policy). The service
 * re-verifies the actor scope at the boundary (§6.2 `adjustment()`).
 */
class QuickStockAdjustmentAction
{
    public static function make(): Action
    {
        return Action::make('quickStockAdjustment')
            ->label(__('resources.products.actions.quick_adjustment'))
            ->modalHeading(__('resources.products.actions.quick_adjustment_heading'))
            ->modalDescription(__('resources.products.actions.quick_adjustment_description'))
            ->icon(Heroicon::AdjustmentsHorizontal)
            ->color('warning')
            ->modalWidth(Width::Large)
            ->authorize('adjustStock')
            ->schema(fn (ProductVariant $record) => [
                Select::make('warehouse_id')
                    ->label(__('resources.products.fields.warehouse'))
                    ->prefixIcon(Heroicon::BuildingOffice2)
                    ->columnSpanFull()
                    ->options(fn () => auth()->user()->isAdmin()
                        ? \App\Models\Warehouse::query()->pluck('name', 'id')
                        : auth()->user()->warehouses()->pluck('name', 'id'))
                    ->default(fn () => auth()->user()->warehouses()->count() === 1
                        ? auth()->user()->warehouses()->first()->id
                        : null)
                    ->required(),

                TextInput::make('signed_base_quantity')
                    ->label(__('resources.products.fields.signed_quantity_base'))
                    ->hintIcon(Heroicon::InformationCircle)
                    ->hint(__('resources.products.hints.signed_quantity'))
                    ->prefixIcon(Heroicon::Hashtag)
                    ->columnSpanFull()
                    ->numeric()
                    ->required(),

                Textarea::make('notes')
                    ->label(__('resources.products.fields.adjustment_notes'))
                    // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                    ->columnSpanFull()
                    ->required()
                    ->minLength(15),
            ])
            ->action(function (array $data, ProductVariant $record) {
                app(InventoryService::class)->adjustment(
                    productVariantId: $record->id,
                    warehouseId: (int) $data['warehouse_id'],
                    signedBaseQuantity: (int) $data['signed_base_quantity'],
                    notes: (string) $data['notes'],
                );

                Notification::make()
                    ->title(__('resources.products.notifications.adjustment_recorded'))
                    ->success()
                    ->send();
            });
    }
}
