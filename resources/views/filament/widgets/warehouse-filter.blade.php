<x-filament-widgets::widget>
    <div class="flex items-center gap-4">
        <div class="flex-1">
            {!! $this->form !!}
        </div>
        @if ($selectedWarehouseId)
            <button
                type="button"
                wire:click="clearFilter"
                class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-100 dark:ring-white/10 dark:hover:bg-white/10"
            >
                <x-heroicon-m-x-mark class="h-4 w-4" />
                Clear
            </button>
        @endif
    </div>
</x-filament-widgets::widget>
