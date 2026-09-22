<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Pages;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

class CreateDirectTransfer extends CreateRecord
{
    protected static string $resource = DirectTransferResource::class;

    protected function getFormSchema(): Schema
    {
        return DirectTransferForm::getLocationSchema();
    }

    protected function getFormStatePath(): string
    {
        return 'wizardData';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormHeading(): string
    {
        return 'INSTANT DIRECT TRANSFER';
    }

    protected function getFormDescription(): string
    {
        return '3-step wizard: origin/destination, stock allocation, review';
    }

    protected function getFormWidth(): string
    {
        return 'max-content';
    }

    protected function getFormActionLabel(): string
    {
        return 'EXECUTE TRANSFER';
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $wizardData = $data['wizardData'] ?? $data;
        $fromId = $wizardData['from_warehouse_id'];
        $toId = $wizardData['to_warehouse_id'];
        $variantId = $wizardData['product_variant_id'];
        $qty = $wizardData['quantity'];
        $notes = $wizardData['notes'];

        $referenceCode = 'DTR-'.date('Ymd').'-'.mb_strtoupper(uniqid());
        $variant = ProductVariant::findOrFail($variantId);

        $movements = app(InventoryService::class)->directTransfer(
            $variantId,
            $fromId,
            $toId,
            $qty,
            unitName: $variant->base_unit_name,
            unitRatio: 1,
            referenceCode: $referenceCode,
            notes: $notes
        );

        $this->record = $movements[0];

        Notification::make()
            ->title('Transfer Executed')
            ->body("Direct transfer #{$this->record->reference_code} completed successfully.")
            ->success()
            ->send();

        return $this->record;
    }

    protected function getWizardSteps(): array
    {
        return [
            Step::make('Location Mapping')
                ->description('Map origin destination warehouses')
                ->schema(DirectTransferForm::getLocationSchema()),

            Step::make('Stock Allocation')
                ->description('Select variant, quantity, add notes')
                ->schema(DirectTransferForm::getAllocationSchema()),

            Step::make('Review & Verify')
                ->description('Confirm all details before executing')
                ->schema(DirectTransferForm::getReviewSchema()),
        ];
    }
}
