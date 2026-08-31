<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Record Adjustment
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
