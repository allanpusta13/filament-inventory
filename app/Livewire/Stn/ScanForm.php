<?php

declare(strict_types=1);

namespace App\Livewire\Stn;

use App\Models\TransferRequisition;
use App\Services\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ScanForm extends Component
{
    public int $requisitionId;

    /** @var array<int, array{received_good: int, received_damaged: int}> */
    public array $lines = [];

    public ?string $error = null;

    public function mount(int $requisition): void
    {
        $requisitionModel = TransferRequisition::findOrFail($requisition);

        $this->requisitionId = $requisitionModel->id;

        foreach ($requisitionModel->items()->orderBy('id')->pluck('id') as $itemId) {
            $this->lines[$itemId] = ['received_good' => 0, 'received_damaged' => 0];
        }
    }

    public function submit(InventoryService $inventory): void
    {
        $this->error = null;

        Gate::authorize('receive', TransferRequisition::findOrFail($this->requisitionId));

        $this->validate([
            'lines'                       => 'required|array|min:1',
            'lines.*.received_good'       => 'required|integer|min:0',
            'lines.*.received_damaged'    => 'required|integer|min:0',
        ]);

        try {
            $inventory->scanToReceive(
                TransferRequisition::findOrFail($this->requisitionId),
                collect($this->lines)
                    ->map(fn ($line) => [
                        'received_good'    => (int) $line['received_good'],
                        'received_damaged' => (int) $line['received_damaged'],
                    ])
                    ->all(),
            );
        } catch (\App\Exceptions\DomainErrorException $e) {
            $this->error = __($e->translationKey() . '.title', $e->context());
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.stn.scan-form', [
            'items' => TransferRequisition::findOrFail($this->requisitionId)
                ->items()->with('productVariant')->orderBy('id')->get(),
        ]);
    }
}
