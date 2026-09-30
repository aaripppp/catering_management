<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Catering Management') }}</title>

        <x-branding-icons />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <main class="flex min-h-screen flex-col bg-slate-100 px-4 py-8 sm:justify-center sm:px-6 sm:py-12">
            <div class="mx-auto w-full max-w-md">
                <div class="flex flex-col items-center text-center">
                    <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                        <x-application-logo class="h-full w-full object-contain" />
                    </div>
                    <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        {{ config('app.name', 'Catering Management') }}
                    </h1>
                    <p class="mt-1.5 text-sm text-slate-500">
                        Sistem Manajemen Catering Sekolah
                    </p>
                </div>

                <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    {{ $slot }}
                </div>

                <footer class="mt-6 text-center text-xs leading-5 text-slate-400">
                    <p>&copy; {{ now()->year }} Catering Management</p>
                    <p>Sistem internal sekolah. Hubungi administrator untuk memperoleh akun.</p>
                </footer>
            </div>
        </main>

    <x-toast :toasts="session('toast') ? [session('toast')] : []" />
</body>
</html>
