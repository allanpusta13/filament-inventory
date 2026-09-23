<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Override;

class CreatePurchaseOrder extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = PurchaseOrderResource::class;

    protected ?string $heading = 'CREATE PURCHASE ORDER';

    protected ?string $subheading = '3-step wizard: supplier & warehouse, line items, review';

    public function getSteps(): array
    {
        return [
            Step::make('Supplier & Warehouse')
                ->description('Select supplier and receiving warehouse')
                ->schema(PurchaseOrderForm::getRoutingSchema()),

            Step::make('Line Items')
                ->description('Add purchase order lines with variants, quantities, and costs')
                ->schema(PurchaseOrderForm::getItemsSchema()),

            Step::make('Review & Confirm')
                ->description('Verify all details before creating the purchase order')
                ->schema(PurchaseOrderForm::getReviewSchema()),
        ];
    }

    #[Override]
    public function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = 'PO-'.date('Ymd').'-'.mb_strtoupper(uniqid());
        $data['status'] = PurchaseOrderStatus::Draft->value;
        $data['ordered_by'] = auth()->user()->id;
        $data['ordered_at'] = now();

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}