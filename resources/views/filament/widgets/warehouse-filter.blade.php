<x-filament-widgets::widget>
    <div class="flex items-center gap-4">
        <div class="flex-1">
            {{ $this->form }}
        </div>
        @if ($selectedWarehouseId)
            <x-filament::button
                tag="button"
                color="gray"
                size="sm"
                wire:click="clearFilter"
                icon="heroicon-m-x-mark"
            >
                Clear
            </x-filament::button>
        @endif
    </div>
</x-filament-widgets::widget>
