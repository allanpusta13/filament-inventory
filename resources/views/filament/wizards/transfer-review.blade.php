<div class="rounded-xl bg-zinc-50 dark:bg-zinc-900 p-4 border border-zinc-200 dark:border-zinc-800">
    <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <span class="text-[10px] uppercase font-bold text-zinc-500">{{ __('resources.transfer_requisitions.review.origin') }}</span>
            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $state['from_warehouse_id'] ? \App\Models\Warehouse::find($state['from_warehouse_id'])?->name : '—' }}</p>
        </div>
        <div>
            <span class="text-[10px] uppercase font-bold text-zinc-500">{{ __('resources.transfer_requisitions.review.destination') }}</span>
            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $state['to_warehouse_id'] ? \App\Models\Warehouse::find($state['to_warehouse_id'])?->name : '—' }}</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-zinc-300 dark:border-zinc-700">
                    <th class="pb-2 font-bold text-zinc-500">{{ __('resources.transfer_requisitions.review.sku') }}</th>
                    <th class="pb-2 font-bold text-zinc-500">{{ __('resources.transfer_requisitions.review.variant') }}</th>
                    <th class="pb-2 font-bold text-zinc-500 text-right">{{ __('resources.transfer_requisitions.review.base_units') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($state['items'] ?? [] as $item)
                    <tr class="border-b border-zinc-200 dark:border-zinc-800">
                        <td class="py-2 font-mono text-xs font-bold text-primary-600">{{ \App\Models\ProductVariant::find($item['product_variant_id'])?->sku ?? '—' }}</td>
                        <td class="py-2 text-xs">{{ \App\Models\ProductVariant::find($item['product_variant_id'])?->name ?? '—' }}</td>
                        <td class="py-2 text-xs text-right font-semibold text-zinc-900 dark:text-zinc-100">{{ $item['base_qty'] ?? 0 }} {{ \App\Models\ProductVariant::find($item['product_variant_id'])?->unit?->name ?? 'Pcs' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(!empty($state['notes']))
    <div class="mt-4 p-3 rounded-lg bg-zinc-100 dark:bg-zinc-800">
        <span class="text-[10px] uppercase font-bold text-zinc-500">{{ __('resources.transfer_requisitions.review.notes') }}</span>
        <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $state['notes'] }}</p>
    </div>
    @endif
</div>