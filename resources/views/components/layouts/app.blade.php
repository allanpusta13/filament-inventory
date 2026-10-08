{{-- resources/views/components/layouts/app.blade.php --}}
@props(['title' => config('app.name')])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body class="bg-white text-zinc-900">
    <main class="mx-auto max-w-3xl space-y-4 p-6">
        {{ $slot }}
    </main>
</body>
</html>
