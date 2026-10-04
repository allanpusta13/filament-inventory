<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use App\Support\GeneratesReferenceCodes;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class CreateSalesOrder extends CreateRecord
{
    use HasWizard;

    protected static string $resource = SalesOrderResource::class;

    public function getMaxContentWidth(): ?string
    {
        return Width::SevenExtraLarge->value;
    }

    /** @return array<Step> */
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
                    View::make('filament.wizards.sales-order-review')
                        ->viewData(fn (Get $get): array => [
                            'state' => [
                                'customer_id' => $get('customer_id'),
                                'warehouse_id' => $get('warehouse_id'),
                                'items' => $get('items') ?? [],
                                'notes' => $get('notes'),
                            ],
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = $data['reference_code']
            ?? GeneratesReferenceCodes::generateReferenceCode('SO');
        $data['ordered_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        Gate::authorize('create', \App\Models\SalesOrder::class);

        return parent::handleRecordCreation($data);
    }
}
