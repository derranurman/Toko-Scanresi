<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $brand->app_name) — {{ $brand->app_name }}</title>
    @if ($brand->logoUrl())
        <link rel="icon" type="image/png" href="{{ $brand->logoUrl() }}">
        <link rel="apple-touch-icon" href="{{ $brand->logoUrl() }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col">
        @include('layouts.partials.nav')

        @if (session('success'))
            <div class="bg-green-50 border-b border-green-200 text-green-800 px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 border-b border-red-200 text-red-800 px-4 py-3 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <main class="flex-1">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                @if (isset($header))
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $header }}</h1>
                        @isset($subheader)
                            <p class="mt-1 text-sm text-gray-500">{{ $subheader }}</p>
                        @endisset
                    </div>
                @endif

                @yield('content')
            </div>
        </main>

        <footer class="border-t border-gray-200 bg-white text-center text-xs text-gray-500 py-3">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </footer>
    </div>
</body>
</html>
