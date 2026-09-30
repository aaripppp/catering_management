<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Catering Management') }}</title>

        <x-branding-icons />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireScripts
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">
            @include('layouts.navigation')

            <div class="min-h-screen lg:pl-72">
                <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
                    <div class="flex min-h-16 items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 lg:hidden"
                                @click="sidebarOpen = ! sidebarOpen"
                                aria-controls="sidebar"
                                aria-label="Buka menu navigasi"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                    <path d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>

                            <div>
                                <h1 class="text-lg font-semibold tracking-tight text-slate-900">{{ $header ?? 'Dashboard' }}</h1>
                                @isset($description)
                                    <p class="mt-0.5 hidden text-xs text-slate-500 sm:block">{{ $description }}</p>
                                @endisset
                            </div>
                        </div>

                        @auth
                            <div class="flex items-center gap-3">
                                <div class="hidden text-right sm:block">
                                    <p class="text-sm font-medium leading-tight text-slate-900">
                                        {{ auth()->user()->name }}
                                    </p>
                                    <p class="text-xs leading-tight text-slate-500">
                                        {{ auth()->user()->role->label() }}
                                    </p>
                                </div>
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700 ring-4 ring-blue-50">
                                    {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                                </span>
                            </div>
                        @endauth
                    </div>
                </header>

                <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mx-auto w-full max-w-screen-2xl">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        <x-toast :toasts="session('toast') ? [session('toast')] : []" />
    </body>
</html>
