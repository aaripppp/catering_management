<x-app-layout>
    <x-slot name="header">
        {{ __('Pengaturan Akun') }}
    </x-slot>
    <x-slot name="description">Kelola profil, password, dan keamanan akun Anda.</x-slot>

    <div class="space-y-6">
        <div class="card p-5 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card p-5 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card p-5 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
