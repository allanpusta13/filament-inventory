<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('reference_code')
                            ->label(__('resources.purchase_orders.table.reference'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable(),

                        TextColumn::make('status')
                            ->label(__('resources.purchase_orders.table.status'))
                            ->badge()
                            ->alignEnd()
                            ->sortable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('supplier.name')
                            ->label(__('resources.purchase_orders.table.supplier'))
                            ->icon(Heroicon::BuildingStorefront)
                            ->iconColor('gray')
                            ->searchable()
                            ->sortable(),

                        TextColumn::make('warehouse.name')
                            ->label(__('resources.purchase_orders.table.warehouse'))
                            ->icon(Heroicon::BuildingOffice2)
                            ->iconColor('gray')
                            ->sortable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('items_count')
                            ->label(__('resources.purchase_orders.table.items'))
                            ->counts('items')
                            ->badge()
                            ->color('gray')
                            ->numeric(),

                        TextColumn::make('ordered_at')
                            ->label(__('resources.purchase_orders.table.ordered'))
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder(__('common.empty')),

                        TextColumn::make('received_at')
                            ->label(__('resources.purchase_orders.table.received'))
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
                SelectFilter::make('status')
                    ->label(__('resources.purchase_orders.filters.status'))
                    ->options(PurchaseOrderStatus::class),
                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label(__('resources.purchase_orders.filters.supplier'))
                    ->searchable(),
                \App\Filament\Support\Filters\AdminReviewFilters::warehouse()
                    ->label(__('resources.purchase_orders.filters.warehouse')),
                TrashedFilter::make(),
                \App\Filament\Support\Filters\AdminReviewFilters::period('ordered_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', PurchaseOrder::class) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn (PurchaseOrder $record) => PurchaseOrderResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye),

                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->visible(fn (PurchaseOrder $record) => $record->status === PurchaseOrderStatus::Draft)
                    ->modalWidth(Width::Large),

                ActionGroup::make([
                    // ── Section: Lifecycle ────────────────────────────────────
                    ActionGroup::make([
                        Action::make('orderPurchase')
                            ->label(__('resources.purchase_orders.actions.order'))
                            ->modalHeading(__('resources.purchase_orders.actions.order_heading'))
                            ->modalDescription(__('resources.purchase_orders.actions.order_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::PaperAirplane)
                            ->color('primary')
                            ->authorize('orderPurchase')
                            ->visible(fn (PurchaseOrder $record) => $record->status === PurchaseOrderStatus::Draft)
                            ->requiresConfirmation()
                            ->action(fn (PurchaseOrder $record) => app(\App\Services\PurchaseService::class)->orderPurchase($record)),

                        Action::make('receivePurchase')
                            ->label(__('resources.purchase_orders.actions.receive'))
                            ->modalHeading(__('resources.purchase_orders.actions.receive_heading'))
                            ->modalDescription(__('resources.purchase_orders.actions.receive_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::ArchiveBoxArrowDown)
                            ->color('success')
                            ->authorize('receivePurchase')
                            ->visible(fn (PurchaseOrder $record) => in_array($record->status, [
                                PurchaseOrderStatus::Ordered,
                                PurchaseOrderStatus::PartiallyReceived,
                            ], true))
                            ->modalWidth(Width::FourExtraLarge)
                            ->schema(fn (PurchaseOrder $record) => collect($record->items)
                                ->map(function ($item) {
                                    $outstandingBase = $item->outstandingBaseQty();
                                    $outstandingDisplay = $item->ordered_unit_ratio > 1
                                        ? round($outstandingBase / $item->ordered_unit_ratio, 2)
                                        : $outstandingBase;

                                    return TextInput::make("received.{$item->id}")
                                        ->label(__('resources.purchase_orders.fields.receive_line', [
                                            'sku' => $item->productVariant->sku,
                                            'outstanding' => $outstandingBase,
                                            'unit' => $item->productVariant->base_unit_name,
                                            'base' => $outstandingBase,
                                        ]))
                                        ->helperText(__('resources.purchase_orders.help.receive_display_equivalent', [
                                            'display' => $outstandingDisplay,
                                            'unit' => $item->ordered_unit_name,
                                        ]))
                                        ->prefixIcon(Heroicon::ArchiveBoxArrowDown)
                                        ->columnSpan(['default' => 1, 'md' => 1])
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue($outstandingBase)
                                        ->default($outstandingBase);
                                })
                                ->all())
                            ->action(function (array $data, PurchaseOrder $record) {
                                $received = collect($data['received'] ?? [])
                                    ->filter(fn ($qty) => (int) $qty > 0)
                                    ->mapWithKeys(fn ($qty, $itemId) => [(int) $itemId => (int) $qty])
                                    ->all();

                                app(\App\Services\PurchaseService::class)->receivePurchase($record->id, $received);

                                Notification::make()
                                    ->title(__('resources.purchase_orders.notifications.received'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),

                        Action::make('cancelPurchase')
                            ->label(__('resources.purchase_orders.actions.cancel'))
                            ->modalHeading(__('resources.purchase_orders.actions.cancel_heading'))
                            ->modalDescription(__('resources.purchase_orders.actions.cancel_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::XMark)
                            ->color('danger')
                            ->authorize('cancelPurchase')
                            ->visible(fn (PurchaseOrder $record) => $record->canBeCancelled())
                            ->requiresConfirmation()
                            ->action(fn (PurchaseOrder $record) => app(\App\Services\PurchaseService::class)->cancelPurchaseOrder($record)),
                    ])->dropdown(false),

                    // ── Section: Destructive ──────────────────────────────────
                    ActionGroup::make([
                        DeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('delete')
                            ->visible(fn (PurchaseOrder $record) => in_array($record->status, [
                                PurchaseOrderStatus::Draft,
                                PurchaseOrderStatus::Cancelled,
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
                    ->tooltip(__('resources.purchase_orders.actions.more_actions'))
                    ->dropdownAutoPlacement()
                    ->dropdownWidth(Width::Large),
            ]);
    }
}
