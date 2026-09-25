<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class CreatePurchaseOrder extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PurchaseOrderResource::class;

    public function getMaxContentWidth(): ?string
    {
        return Width::SevenExtraLarge->value;
    }

    protected function getSteps(): array
    {
        return [
            Step::make(__('resources.purchase_orders.steps.supplier_warehouse'))
                ->description(__('resources.purchase_orders.steps.supplier_warehouse_description'))
                ->icon(Heroicon::BuildingStorefront)
                ->schema(PurchaseOrderForm::getSupplierWarehouseFields()),

            Step::make(__('resources.purchase_orders.steps.line_items'))
                ->description(__('resources.purchase_orders.steps.line_items_description'))
                ->icon(Heroicon::ClipboardDocumentList)
                ->schema(PurchaseOrderForm::getLineItemsFields()),

            Step::make(__('resources.purchase_orders.steps.review_verify'))
                ->description(__('resources.purchase_orders.steps.review_verify_description'))
                ->icon(Heroicon::CheckCircle)
                ->schema([
                    ...PurchaseOrderForm::getReviewFields(),
                    Placeholder::make('review_summary')
                        ->columnSpanFull()
                        ->content(fn (Get $get) => view(
                            'filament.wizards.purchase-order-review',
                            ['state' => $get()],
                        )),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = $data['reference_code']
            ?? 'PO-'.now()->format('YmdHis').'-'.random_int(100, 999);
        $data['ordered_by'] = auth()->id();

        return $data;
    }
}
