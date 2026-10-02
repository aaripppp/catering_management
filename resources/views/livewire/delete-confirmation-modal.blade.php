<div
    x-show="$wire.open"
    x-scroll-lock="$wire.open"
    x-on:keydown.escape.window="$wire.closeModal()"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="delete-modal-title"
>
    <div
        x-show="$wire.open"
        class="fixed inset-0"
        wire:click="closeModal"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-[1px]"></div>
    </div>

    <div
        x-show="$wire.open"
        class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl transform transition-all"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
            <div>
                <h3 id="delete-modal-title" class="text-base font-semibold text-slate-900">Konfirmasi Hapus</h3>
                <p class="mt-1 text-sm text-slate-500">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <button
                type="button"
                wire:click="closeModal"
                class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                aria-label="Tutup"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
                </svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4 sm:px-6">
            <p class="text-sm text-slate-700">{{ $message }}</p>
            @if ($recordName)
                <p class="mt-2 font-medium text-slate-900">"{{ $recordName }}"</p>
            @endif
        </div>

        <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
            <button
                type="button"
                wire:click="closeModal"
                class="btn-secondary"
            >
                Batal
            </button>
            <button
                type="button"
                wire:click="confirm"
                class="btn-danger"
            >
                Hapus
            </button>
        </div>
    </div>
</div>
