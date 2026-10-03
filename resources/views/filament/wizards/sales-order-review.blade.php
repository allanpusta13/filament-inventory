{{-- resources/views/filament/wizards/sales-order-review.blade.php --}}
@php
$customerName = isset($state['customer_id']) ? \App\Models\Customer::find($state['customer_id'])?->name : null;
$warehouseName = isset($state['warehouse_id']) ? \App\Models\Warehouse::find($state['warehouse_id'])?->name : null;
@endphp
<div class="space-y-4">
    <h3 class="text-base font-semibold">{{ __('wizards.sales_order_review.title') }}</h3>
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <dt class="text-zinc-500">{{ __('wizards.sales_order_review.customer') }}</dt>
            <dd class="font-medium">{{ $customerName ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('wizards.sales_order_review.warehouse') }}</dt>
            <dd class="font-medium">{{ $warehouseName ?? '—' }}</dd>
        </div>
    </dl>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-zinc-500">
                <th>{{ __('wizards.sales_order_review.sku') }}</th>
                <th>{{ __('wizards.sales_order_review.qty') }}</th>
                <th>{{ __('wizards.sales_order_review.unit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($state['items'] ?? []) as $item)
            @php($sku = isset($item['product_variant_id']) ?
            \App\Models\ProductVariant::find($item['product_variant_id'])?->sku : null)
            <tr class="border-t border-zinc-200">
                <td class="font-mono">{{ $sku ?? '—' }}</td>
                <td>{{ $item['qty'] ?? 0 }}</td>
                <td>{{ $item['unit_name'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if (! empty($state['notes']))
    <p class="text-sm text-zinc-500">{{ $state['notes'] }}</p>
    @endif
</div>