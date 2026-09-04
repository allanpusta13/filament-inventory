<div class="grid grid-cols-1 gap-4 mb-6">
    @foreach ($stats as $stat)
        <div class="glass-panel-light dark:glass-panel-dark p-6 rounded-xl transition-all duration-300 hover:scale-[1.01] hover:translate-y-[-2px] hover:shadow-2xl">
            <div class="flex items-center justify-between mb-2">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-500">{{ $stat->getLabel() }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stat->getValue() }}</p>
                </div>
                <div class="flex-shrink-0">
                    {!! $stat->getIcon() !!}
                </div>
            </div>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">{{ $stat->getDescription() }}</p>
        </div>
    @endforeach
</div>