<div class="text-sm text-gray-700 dark:text-gray-300">
    @if (empty($names))
    {{ __('resources.warehouses.empty_staff') }}
    @else
    {{ implode(', ', $names) }}
    @endif
</div>