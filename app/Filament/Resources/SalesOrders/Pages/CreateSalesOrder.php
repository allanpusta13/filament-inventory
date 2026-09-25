<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class CreateSalesOrder extends CreateRecord
{
    use HasWizard;

    protected static string $resource = SalesOrderResource::class;

    public function getMaxContentWidth(): ?string
    {
        return Width::SevenExtraLarge->value;
    }

    protected function getSteps(): array
    {
        return [
            Step::make(__('resources.sales_orders.steps.customer_warehouse'))
                ->description(__('resources.sales_orders.steps.customer_warehouse_description'))
                ->icon(Heroicon::UserGroup)
                ->schema(SalesOrderForm::getCustomerWarehouseFields()),

            Step::make(__('resources.sales_orders.steps.line_items'))
                ->description(__('resources.sales_orders.steps.line_items_description'))
                ->icon(Heroicon::ClipboardDocumentList)
                ->schema(SalesOrderForm::getLineItemsFields()),

            Step::make(__('resources.sales_orders.steps.review_verify'))
                ->description(__('resources.sales_orders.steps.review_verify_description'))
                ->icon(Heroicon::CheckCircle)
                ->schema([
                    Placeholder::make('review_summary')
                        ->columnSpanFull()
                        ->content(fn (Get $get) => view(
                            'filament.wizards.sales-order-review',
                            ['state' => $get()],
                        )),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = $data['reference_code']
            ?? 'SO-'.now()->format('YmdHis').'-'.random_int(100, 999);
        $data['ordered_by'] = auth()->id();

        return $data;
    }
}
