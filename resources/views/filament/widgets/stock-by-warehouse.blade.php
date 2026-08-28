<x-filament-widgets::widget>
    <x-filament::section heading="Stock by Warehouse">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($warehouses as $warehouse)
                <div class="fi-card relative flex flex-col overflow-hidden rounded-xl bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 p-6 shadow-sm transition-all duration-200 hover:shadow-md">
                    <div class="flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                            <x-heroicon-o-building-office-2 class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        </div>
                        @if ($warehouse['is_active'])
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 ring-1 ring-inset ring-emerald-600/20 dark:ring-emerald-500/20">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-500/10 dark:text-gray-400 ring-1 ring-inset ring-gray-500/10">
                                Inactive
                            </span>
                        @endif
                    </div>

                    <div class="mt-4">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $warehouse['name'] }}</h3>
                        @if ($warehouse['location'])
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $warehouse['location'] }}</p>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Stock Qty</p>
                            <p class="mt-0.5 text-lg font-bold {{ $warehouse['total_quantity'] <= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-950 dark:text-white' }}">
                                {{ number_format($warehouse['total_quantity']) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Products</p>
                            <p class="mt-0.5 text-lg font-bold text-gray-950 dark:text-white">
                                {{ number_format($warehouse['product_count']) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-gray-100 dark:border-white/10 pt-3">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($warehouse['total_movements']) }} movements</span>
                        <x-heroicon-m-arrow-right class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                    </div>
                </div>
            @empty
                <div class="col-span-full flex flex-col items-center justify-center py-12 text-center">
                    <x-heroicon-o-building-office-2 class="h-12 w-12 text-gray-400 dark:text-gray-500" />
                    <p class="mt-2 text-sm font-medium text-gray-950 dark:text-white">No warehouses found</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Get started by creating a warehouse.</p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
