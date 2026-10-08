{{-- resources/views/livewire/stn/scan-form.blade.php --}}
<form wire:submit="submit" class="space-y-4">
    @if ($error)
        <p class="text-sm text-red-600">{{ $error }}</p>
    @endif
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-zinc-500">
                <th>{{ __('stn.scan.sku') }}</th>
                <th>{{ __('stn.scan.received_good') }}</th>
                <th>{{ __('stn.scan.received_damaged') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr class="border-t border-zinc-200">
                    <td class="font-mono">{{ $item->productVariant->sku }}</td>
                    <td><input type="number" min="0" step="1" wire:model="lines.{{ $item->id }}.received_good" class="w-24 border px-2 py-1"></td>
                    <td><input type="number" min="0" step="1" wire:model="lines.{{ $item->id }}.received_damaged" class="w-24 border px-2 py-1"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button type="submit" class="no-print bg-zinc-900 px-4 py-2 text-sm font-medium text-white">{{ __('stn.scan.submit') }}</button>
</form>
