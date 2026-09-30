<x-guest-layout>
    <div class="text-center">
        <h2 class="text-xl font-semibold tracking-tight text-slate-900">Catering Management</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">
            Sistem internal pengelolaan layanan catering sekolah Annur.
        </p>

        @auth
            <a href="{{ route('dashboard') }}" class="btn-primary mt-6 w-full">Buka Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="btn-primary mt-6 w-full">Masuk</a>
        @endauth
    </div>
</x-guest-layout>
