{{-- resources/views/stn/scan.blade.php --}}
<x-layouts.app :title="__('stn.scan.title', ['ref' => $requisition->reference_code])">
    <h1 class="text-lg font-semibold">
        {{ __('stn.scan.heading') }} — <span class="font-mono">{{ $requisition->reference_code }}</span>
    </h1>
    <p class="text-sm text-zinc-500">
        {{ __('stn.scan.route', ['from' => $requisition->fromWarehouse->name, 'to' => $requisition->toWarehouse->name]) }}
    </p>
    <livewire:stn.scan-form :requisition="$requisition->id" />
</x-layouts.app>
