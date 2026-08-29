<x-filament-widgets::widget>
    <div class="fi-card">
        <div class="fi-card-header flex items-center justify-between border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Warehouse Inventory Summary</h2>
        </div>
        <div class="fi-card-content p-6">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($warehouses as $warehouse)
                    <div class="relative flex flex-col overflow-hidden rounded-lg bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 p-6 transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-white/[0.04]">
                        <div class="flex items-start justify-between">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20" class="text-primary-600 dark:text-primary-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                            </div>
                            @if ($warehouse['is_active'])
                                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 ring-1 ring-inset ring-emerald-600/20 dark:ring-emerald-500/20">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-600 dark:bg-gray-500/10 dark:text-gray-400 ring-1 ring-inset ring-gray-500/10">
                                    Inactive
                                </span>
                            @endif
                        </div>

                        <div class="mt-5">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $warehouse['name'] }}</h3>
                            @if ($warehouse['location'])
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ $warehouse['location'] }}</p>
                            @endif
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Stock Qty</p>
                                <p class="mt-1 text-2xl font-bold {{ $warehouse['total_quantity'] <= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-950 dark:text-white' }} font-tabular-nums">
                                    {{ number_format($warehouse['total_quantity']) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Products</p>
                                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white font-tabular-nums">
                                    {{ number_format($warehouse['product_count']) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-between border-t border-gray-100 dark:border-white/10 pt-4">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ number_format($warehouse['total_movements']) }} movements</span>
                            <div class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16" class="text-gray-400 dark:text-gray-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                                <span class="sr-only">View details</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full flex flex-col items-center justify-center py-12 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="48" height="48" class="text-gray-400 dark:text-gray-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                        <p class="mt-3 text-base font-medium text-gray-950 dark:text-white">No warehouses found</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Get started by creating a warehouse.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
