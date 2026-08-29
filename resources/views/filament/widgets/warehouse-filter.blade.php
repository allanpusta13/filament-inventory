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
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
                Clear
            </button>
        @endif
    </div>
</x-filament-widgets::widget>
