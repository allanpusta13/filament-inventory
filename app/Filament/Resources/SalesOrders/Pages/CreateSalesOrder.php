<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Override;

class CreateSalesOrder extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = SalesOrderResource::class;

    protected ?string $heading = 'CREATE SALES ORDER';

    protected ?string $subheading = '3-step wizard: customer & warehouse, line items, review';

    public function getSteps(): array
    {
        return [
            Step::make('Customer & Warehouse')
                ->description('Select customer and dispatch warehouse')
                ->schema(SalesOrderForm::getRoutingSchema()),

            Step::make('Line Items')
                ->description('Add sales order lines with variants, quantities, and price preview')
                ->schema(SalesOrderForm::getItemsSchema()),

            Step::make('Review & Confirm')
                ->description('Verify all details before creating the sales order')
                ->schema(SalesOrderForm::getReviewSchema()),
        ];
    }

    #[Override]
    public function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = 'SO-'.date('Ymd').'-'.mb_strtoupper(uniqid());
        $data['status'] = SalesOrderStatus::Draft->value;
        $data['ordered_by'] = auth()->user()->id;
        $data['ordered_at'] = now();

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}