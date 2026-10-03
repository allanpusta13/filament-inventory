{{-- resources/views/filament/wizards/purchase-order-review.blade.php --}}
@php
$supplierName = isset($state['supplier_id']) ? \App\Models\Supplier::find($state['supplier_id'])?->name : null;
$warehouseName = isset($state['warehouse_id']) ? \App\Models\Warehouse::find($state['warehouse_id'])?->name : null;
@endphp
<div class="space-y-4">
    <h3 class="text-base font-semibold">{{ __('wizards.purchase_order_review.title') }}</h3>
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <dt class="text-zinc-500">{{ __('wizards.purchase_order_review.supplier') }}</dt>
            <dd class="font-medium">{{ $supplierName ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('wizards.purchase_order_review.warehouse') }}</dt>
            <dd class="font-medium">{{ $warehouseName ?? '—' }}</dd>
        </div>
    </dl>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-zinc-500">
                <th>{{ __('wizards.purchase_order_review.sku') }}</th>
                <th>{{ __('wizards.purchase_order_review.qty') }}</th>
                <th>{{ __('wizards.purchase_order_review.unit_cost') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($state['items'] ?? []) as $item)
            @php($sku = isset($item['product_variant_id']) ?
            \App\Models\ProductVariant::find($item['product_variant_id'])?->sku : null)
            <tr class="border-t border-zinc-200">
                <td class="font-mono">{{ $sku ?? '—' }}</td>
                <td>{{ $item['ordered_qty'] ?? 0 }}</td>
                <td>{{ $item['unit_cost_price'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if (! empty($state['notes']))
    <p class="text-sm text-zinc-500">{{ $state['notes'] }}</p>
    @endif
</div>