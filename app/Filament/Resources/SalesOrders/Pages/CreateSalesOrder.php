<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use App\Livewire\Wizards\WizardReviewSummary;
use App\Support\GeneratesReferenceCodes;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Utilities\Get;
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
                    Livewire::make(WizardReviewSummary::class, fn (Get $get): array => [
                        'view' => 'filament.wizards.sales-order-review',
                        'state' => $get(),
                    ])->columnSpanFull(),
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
