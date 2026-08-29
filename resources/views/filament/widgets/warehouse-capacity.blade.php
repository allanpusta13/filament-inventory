<div class="space-y-4" data-testid="warehouse-capacity-panel">
    <div class="space-y-3">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Warehouse Stock Distribution</h3>
        @php
            $warehouses = $this->getWarehouses();
            $maxQty = collect($warehouses)->max('total_quantity') ?: 1;
        @endphp

        @forelse ($warehouses as $warehouse)
            @php
                $percentage = round(($warehouse['total_quantity'] / $maxQty) * 100);
                $barColor = $percentage > 80 ? 'bg-emerald-500' : ($percentage > 40 ? 'bg-blue-500' : 'bg-amber-500');
            @endphp
            <div class="space-y-1" data-testid="capacity-bar-{{ $warehouse['id'] }}">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $warehouse['name'] }}</span>
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ number_format($warehouse['total_quantity']) }} units &middot; {{ $warehouse['product_count'] }} products
                    </span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div
                        class="h-full rounded-full transition-all duration-500 ease-out {{ $barColor }}"
                        style="width: {{ $percentage }}%"
                        role="progressbar"
                        aria-valuenow="{{ $warehouse['total_quantity'] }}"
                        aria-valuemin="0"
                        aria-valuemax="{{ $maxQty }}"
                        aria-label="{{ $warehouse['name'] }} capacity: {{ $percentage }}%"
                    ></div>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $warehouse['location'] }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No active warehouses found.</p>
        @endforelse
    </div>

    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Recent Mutations</h3>
        @php
            $mutations = $this->getRecentMutations();
        @endphp

        <div class="space-y-2 max-h-64 overflow-y-auto" role="log" aria-label="Recent stock mutations" data-testid="audit-log">
            @forelse ($mutations as $mutation)
                @php
                    $typeColors = match($mutation['type']) {
                        'receive', 'transfer_in' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                        'ship', 'transfer_out' => 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
                        'adjustment' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
                    };
                    $typeLabel = match($mutation['type']) {
                        'receive' => 'In',
                        'ship' => 'Out',
                        'transfer_in' => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        'adjustment' => 'Adj',
                        default => $mutation['type'],
                    };
                    $qtyColor = $mutation['quantity'] > 0
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-rose-600 dark:text-rose-400';
                    $qtyPrefix = $mutation['quantity'] > 0 ? '+' : '';
                @endphp
                <div class="flex items-start gap-2 text-xs p-2 rounded-lg bg-gray-50 dark:bg-gray-800/50" data-testid="audit-log-entry">
                    <span class="inline-flex items-center rounded-md px-1.5 py-0.5 font-medium {{ $typeColors }}">
                        {{ $typeLabel }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-700 dark:text-gray-300 truncate">{{ $mutation['product'] }}</p>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ $mutation['warehouse'] }} &middot;
                            <span class="{{ $qtyColor }}">{{ $qtyPrefix }}{{ number_format($mutation['quantity']) }}</span>
                        </p>
                    </div>
                    <time class="text-gray-400 dark:text-gray-500 whitespace-nowrap" datetime="{{ $mutation['created_at'] }}">
                        {{ $mutation['created_at'] }}
                    </time>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No recent mutations.</p>
            @endforelse
        </div>
    </div>
</div>
