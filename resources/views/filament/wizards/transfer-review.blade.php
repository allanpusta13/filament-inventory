{{-- resources/views/filament/wizards/transfer-review.blade.php --}}
@php
// Wizard state carries foreign keys (§7B.1: `from_warehouse_id`,
// `to_warehouse_id`, repeater `product_variant_id`). Display names
// are resolved from those keys at render time — never stored in the
// state.
$fromName = isset($state['from_warehouse_id']) ? \App\Models\Warehouse::find($state['from_warehouse_id'])?->name : null;
$toName = isset($state['to_warehouse_id']) ? \App\Models\Warehouse::find($state['to_warehouse_id'])?->name : null;
@endphp
<div class="space-y-4">
    <h3 class="text-base font-semibold">{{ __('wizards.transfer_review.title') }}</h3>
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <dt class="text-zinc-500">{{ __('wizards.transfer_review.from') }}</dt>
            <dd class="font-medium">{{ $fromName ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('wizards.transfer_review.to') }}</dt>
            <dd class="font-medium">{{ $toName ?? '—' }}</dd>
        </div>
    </dl>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-zinc-500">
                <th>{{ __('wizards.transfer_review.sku') }}</th>
                <th>{{ __('wizards.transfer_review.qty') }}</th>
                <th>{{ __('wizards.transfer_review.unit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($state['items'] ?? []) as $item)
            @php($sku = isset($item['product_variant_id']) ?
            \App\Models\ProductVariant::find($item['product_variant_id'])?->sku : null)
            <tr class="border-t border-zinc-200">
                <td class="font-mono">{{ $sku ?? '—' }}</td>
                <td>{{ $item['requested_qty'] ?? 0 }}</td>
                <td>{{ $item['requested_unit_name'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if (! empty($state['notes']))
    <p class="text-sm text-zinc-500">{{ $state['notes'] }}</p>
    @endif
</div>