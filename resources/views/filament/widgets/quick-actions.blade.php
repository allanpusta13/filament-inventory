<x-filament-widgets::widget>
    <div class="flex flex-col gap-3">
        @foreach ($this->getActions() as $action)
            {{ $action }}
        @endforeach
    </div>
</x-filament-widgets::widget>
