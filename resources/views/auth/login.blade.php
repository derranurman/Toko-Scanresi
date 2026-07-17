<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — {{ $brand->app_name }}</title>
    @if ($brand->logoUrl())
        <link rel="icon" type="image/png" href="{{ $brand->logoUrl() }}">
        <link rel="apple-touch-icon" href="{{ $brand->logoUrl() }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-50 to-white grid place-items-center px-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            @if ($brand->logoUrl())
                <div class="h-12 w-12 rounded-xl overflow-hidden mx-auto">
                    <img src="{{ $brand->logoUrl() }}" alt="Logo {{ $brand->app_name }}" class="h-full w-full object-cover">
                </div>
            @else
                <div class="h-12 w-12 rounded-xl bg-indigo-600 text-white grid place-items-center font-bold text-xl mx-auto">{{ $brand->initial() }}</div>
            @endif
            <h1 class="mt-3 text-2xl font-bold">{{ $brand->app_name }}</h1>
            <p class="text-sm text-gray-500">Masuk untuk mengelola stok & scan resi</p>
        </div>

        <div class="card">
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="login">Email / Username</label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus class="input">
                    @error('login') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="password">Password</label>
                    <input id="password" name="password" type="password" required class="input">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Ingat saya
                </label>

                <button type="submit" class="btn-primary w-full">Masuk</button>
            </form>
        </div>
    </div>
</body>
</html>
