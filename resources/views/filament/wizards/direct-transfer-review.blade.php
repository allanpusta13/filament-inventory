<div class="rounded-xl bg-zinc-50 dark:bg-zinc-900 p-4 border border-zinc-200 dark:border-zinc-800">
    <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <span class="text-[10px] uppercase font-bold text-zinc-500">ORIGIN</span>
            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $fromWarehouse?->name ?? '—' }}</p>
        </div>
        <div>
            <span class="text-[10px] uppercase font-bold text-zinc-500">DESTINATION</span>
            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $toWarehouse?->name ?? '—' }}</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-zinc-300 dark:border-zinc-700">
                    <th class="pb-2 font-bold text-zinc-500">SKU</th>
                    <th class="pb-2 font-bold text-zinc-500">VARIANT</th>
                    <th class="pb-2 font-bold text-zinc-500 text-right">BASE UNITS</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-zinc-200 dark:border-zinc-800">
                    <td class="py-2 font-mono text-xs font-bold text-primary-600">{{ $productVariant?->sku ?? '—' }}</td>
                    <td class="py-2 text-xs">{{ $productVariant?->name ?? '—' }}</td>
                    <td class="py-2 text-xs text-right font-semibold text-zinc-900 dark:text-zinc-100">{{ $quantity ?? 0 }} Pcs</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if(!empty($notes))
    <div class="mt-4 p-3 rounded-lg bg-zinc-100 dark:bg-zinc-800">
        <span class="text-[10px] uppercase font-bold text-zinc-500">NOTES</span>
        <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $notes }}</p>
    </div>
    @endif
</div>
