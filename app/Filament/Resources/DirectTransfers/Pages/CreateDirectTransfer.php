<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Pages;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;

class CreateDirectTransfer extends CreateRecord
{
    use HasWizard;

    protected static string $resource = DirectTransferResource::class;

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

    protected function getSubmitFormAction(): Action
    {
        return Action::make('create')
            ->label('EXECUTE TRANSFER')
            ->color('primary')
            ->action('create');
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $wizardData = $data['wizardData'] ?? $data;
        $fromId = (int) $wizardData['from_warehouse_id'];
        $toId = (int) $wizardData['to_warehouse_id'];
        $variantId = (int) $wizardData['product_variant_id'];
        $qty = (int) $wizardData['quantity'];
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

    protected function getSteps(): array
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
