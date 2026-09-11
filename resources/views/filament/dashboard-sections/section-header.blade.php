@props(['title', 'description' => null, 'icon' => null, 'iconColor' => 'primary'])

<div class="flex grow items-start justify-between gap-4 py-2">
    <div class="flex items-center gap-3">
        @if ($icon)
        <div
            class="flex h-10 w-10 items-center justify-center rounded-lg bg-{{$iconColor}}-50 dark:bg-{{$iconColor}}-500/10">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" width="20" height="20"
                class="text-{{$iconColor}}-600 dark:text-{{$iconColor}}-400">
                @switch ($icon)
                @case ('chart')
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3.75 3v11.25A2.25 2.25 0 0 1 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m-15 0a2.25 2.25 0 0 0-2.25 2.25v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V6.75A2.25 2.25 0 0 0 18.75 4.5m-15 0v-1.5c0-.621.504-1.125 1.125-1.125h15.75c.621 0 1.125.504 1.125 1.125V3" />
                @break
                @case ('alert')
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 15.75h.007v.008H12v-.008Z" />
                @break
                @case ('warehouse')
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                @break
                @case ('trend')
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                @break
                @case ('activity')
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M15.75 17.25a3 3 0 0 1-2.354 5.066M15 4.5 3 16.5M21 12H3m18 0 4.5 4.5M21 12l-4.5 4.5M3 12h18" />
                @break
                @case ('speed')
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                @break
                @endswitch
            </svg>
        </div>
        @endif
        <div class="flex flex-col grow">
            <h2 class="fi-section-header text-xl">{{ $title }}</h2>
            @if ($description)
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>
    </div>
</div>