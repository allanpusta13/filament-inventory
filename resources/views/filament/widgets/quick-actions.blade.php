{{-- resources/views/filament/widgets/quick-actions.blade.php --}}
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    @foreach ($actions as $action)
    <a href="{{ $action['url'] }}"
        class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm font-medium text-zinc-900 hover:bg-zinc-50">
        <x-filament::icon :icon="$action['icon']" class="h-5 w-5 text-zinc-500" />
        {{ $action['label'] }}
    </a>
    @endforeach
</div>