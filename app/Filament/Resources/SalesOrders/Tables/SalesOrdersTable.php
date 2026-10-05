<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('reference_code')
                            ->label(__('resources.sales_orders.table.reference'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable(),

                        TextColumn::make('status')
                            ->label(__('resources.sales_orders.table.status'))
                            ->badge()
                            ->alignEnd()
                            ->sortable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('customer.name')
                            ->label(__('resources.sales_orders.table.customer'))
                            ->icon(Heroicon::UserGroup)
                            ->iconColor('gray')
                            ->searchable()
                            ->sortable(),

                        TextColumn::make('warehouse.name')
                            ->label(__('resources.sales_orders.table.warehouse'))
                            ->icon(Heroicon::BuildingOffice2)
                            ->iconColor('gray')
                            ->sortable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('items_count')
                            ->label(__('resources.sales_orders.table.items'))
                            ->counts('items')
                            ->badge()
                            ->color('gray')
                            ->numeric(),

                        TextColumn::make('confirmed_at')
                            ->label(__('resources.sales_orders.table.confirmed'))
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder(__('common.empty'))
                            ->visibleFrom('md'),

                        TextColumn::make('dispatched_at')
                            ->label(__('resources.sales_orders.table.dispatched'))
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder(__('common.empty'))
                            ->alignEnd(),
                    ])->from('lg'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label(__('resources.sales_orders.filters.status'))
                    ->options(SalesOrderStatus::class),
                \Filament\Tables\Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->label(__('resources.sales_orders.filters.customer'))
                    ->searchable(),
                \App\Filament\Support\Filters\AdminReviewFilters::warehouse()
                    ->label(__('resources.sales_orders.filters.warehouse')),
                \Filament\Tables\Filters\TrashedFilter::make(),
                \App\Filament\Support\Filters\AdminReviewFilters::period('confirmed_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', SalesOrder::class) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn (SalesOrder $record) => SalesOrderResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye),

                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->visible(fn (SalesOrder $record) => $record->status === SalesOrderStatus::Draft)
                    ->modalWidth(Width::Large),

                ActionGroup::make([
                    // ── Section: Lifecycle ────────────────────────────────────
                    ActionGroup::make([
                        Action::make('confirmSalesOrder')
                            ->label(__('resources.sales_orders.actions.confirm'))
                            ->modalHeading(__('resources.sales_orders.actions.confirm_heading'))
                            ->modalDescription(__('resources.sales_orders.actions.confirm_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::CheckCircle)
                            ->color('primary')
                            ->authorize('confirmSalesOrder')
                            ->visible(fn (SalesOrder $record) => $record->status === SalesOrderStatus::Draft)
                            ->requiresConfirmation()
                            ->action(fn (SalesOrder $record) => app(\App\Services\SalesService::class)->confirmSalesOrder($record)),

                        Action::make('dispatchSale')
                            ->label(__('resources.sales_orders.actions.dispatch'))
                            ->modalHeading(__('resources.sales_orders.actions.dispatch_heading'))
                            ->modalDescription(__('resources.sales_orders.actions.dispatch_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::Truck)
                            ->color('success')
                            ->authorize('dispatchSale')
                            ->visible(fn (SalesOrder $record) => in_array($record->status, [
                                SalesOrderStatus::Confirmed,
                                SalesOrderStatus::PartiallyDispatched,
                            ], true))
                            ->modalWidth(Width::FourExtraLarge)
                            ->schema(function (SalesOrder $record) {
                                $variantIds = $record->items->pluck('product_variant_id')->unique()->all();

                                $availableByVariant = \App\Models\ProductVariant::batchAvailableQuantity(
                                    $variantIds,
                                    $record->warehouse_id,
                                    $record->id,
                                );

                                return collect($record->items)
                                    ->map(function ($item) use ($availableByVariant) {
                                        $available = $availableByVariant[$item->product_variant_id] ?? 0;
                                        $safeMax = min($item->outstandingBaseQty(), max(0, $available));

                                        $helperText = null;
                                        if ($available === 0) {
                                            $helperText = __('resources.sales_orders.help.no_stock');
                                        } elseif ($available < $item->outstandingBaseQty()) {
                                            $helperText = __('resources.sales_orders.help.insufficient_stock');
                                        }

                                        return TextInput::make("dispatch.{$item->id}")
                                            ->label(__('resources.sales_orders.fields.dispatch_line', [
                                                'sku' => $item->productVariant->sku,
                                                'outstanding' => $item->outstandingBaseQty(),
                                                'unit' => $item->unit_name,
                                                'available' => $available,
                                            ]))
                                            ->prefixIcon(Heroicon::Truck)
                                            ->columnSpan(['default' => 1, 'md' => 1])
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue($safeMax)
                                            ->default($safeMax)
                                            ->helperText($helperText);
                                    })
                                    ->all();
                            })
                            ->action(function (array $data, SalesOrder $record) {
                                $dispatch = collect($data['dispatch'] ?? [])
                                    ->filter(fn ($qty) => (int) $qty > 0)
                                    ->mapWithKeys(fn ($qty, $itemId) => [(int) $itemId => (int) $qty])
                                    ->all();

                                app(\App\Services\SalesService::class)->dispatchSale($record->id, $dispatch);

                                Notification::make()
                                    ->title(__('resources.sales_orders.notifications.dispatched'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),

                        Action::make('recordReturn')
                            ->label(__('resources.sales_orders.actions.return'))
                            ->modalHeading(__('resources.sales_orders.actions.return_heading'))
                            ->modalDescription(__('resources.sales_orders.actions.return_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->color('warning')
                            ->authorize('recordSalesReturn')
                            ->visible(fn (SalesOrder $record) => $record->items->contains(fn ($item) => $item->dispatched_base_qty > 0))
                            ->modalWidth(Width::Large)
                            ->schema([
                                Select::make('sales_order_item_id')
                                    ->label(__('resources.sales_orders.fields.line_item'))
                                    ->prefixIcon(Heroicon::ClipboardDocumentList)
                                    ->columnSpan(['default' => 1, 'md' => 1])
                                    ->options(fn (SalesOrder $record) => $record->items
                                        ->where('dispatched_base_qty', '>', 0)
                                        ->mapWithKeys(fn ($item) => [
                                            $item->id => __('resources.sales_orders.fields.return_option', [
                                                'sku' => $item->productVariant->sku,
                                                'dispatched' => $item->dispatched_base_qty,
                                                'returned' => $item->alreadyReturnedBaseQty(),
                                            ]),
                                        ]))
                                    ->required()
                                    ->live(),

                                TextInput::make('returned_base_qty')
                                    ->label(__('resources.sales_orders.fields.returned_qty_base'))
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->columnSpan(['default' => 1, 'md' => 1])
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(function (\Filament\Schemas\Components\Utilities\Get $get, SalesOrder $record) {
                                        $itemId = $get('sales_order_item_id');
                                        if (! $itemId) {
                                            return null;
                                        }
                                        $item = $record->items->firstWhere('id', (int) $itemId);

                                        return $item ? ($item->dispatched_base_qty - $item->alreadyReturnedBaseQty()) : null;
                                    })
                                    ->required(),

                                Textarea::make('notes')
                                    ->label(__('resources.sales_orders.fields.notes'))
                                    ->columnSpanFull(),
                            ])
                            ->action(function (array $data) {
                                app(\App\Services\SalesService::class)->recordSalesReturn(
                                    (int) $data['sales_order_item_id'],
                                    (int) $data['returned_base_qty'],
                                    $data['notes'] ?? null,
                                );

                                Notification::make()
                                    ->title(__('resources.sales_orders.notifications.return_recorded'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),

                        Action::make('cancelSalesOrder')
                            ->label(__('resources.sales_orders.actions.cancel'))
                            ->modalHeading(__('resources.sales_orders.actions.cancel_heading'))
                            ->modalDescription(__('resources.sales_orders.actions.cancel_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::XMark)
                            ->color('danger')
                            ->authorize('cancelSalesOrder')
                            ->visible(fn (SalesOrder $record) => $record->canBeCancelled())
                            ->requiresConfirmation()
                            ->action(fn (SalesOrder $record) => app(\App\Services\SalesService::class)->cancelSalesOrder($record)),
                    ])->dropdown(false),

                    // ── Section: Destructive ──────────────────────────────────
                    ActionGroup::make([
                        DeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('delete')
                            ->visible(fn (SalesOrder $record) => in_array($record->status, [
                                SalesOrderStatus::Draft,
                                SalesOrderStatus::Cancelled,
                            ], true)),

                        RestoreAction::make()
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->authorize('restore'),

                        ForceDeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('forceDelete')
                            ->visible(fn () => auth()->user()->isAdmin()),
                    ])->dropdown(false),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->iconButton()
                    ->size(Size::Small)
                    ->color('gray')
                    ->tooltip(__('resources.sales_orders.actions.more_actions'))
                    ->dropdownAutoPlacement()
                    ->dropdownWidth(Width::Large),
            ]);
    }
}
