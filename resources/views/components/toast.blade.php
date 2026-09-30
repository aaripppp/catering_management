@props(['toasts' => []])

<div
    x-data="{
        show: false,
        message: '',
        type: 'success',
        timer: null,
        openToast(detail) {
            clearTimeout(this.timer);

            this.message = detail.message;
            this.type = detail.type;
            this.show = true;

            this.timer = setTimeout(() => {
                this.show = false;
            }, 3000);
        },
        closeToast() {
            clearTimeout(this.timer);
            this.show = false;
        }
    }"
    x-init="@js($toasts[0] ?? null) && openToast(@js($toasts[0] ?? null))"
    x-on:toast.window="openToast($event.detail)"
    class="fixed top-4 right-4 z-[100] pointer-events-none"
>
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="transform opacity-0 translate-x-full"
        x-transition:enter-end="transform opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="transform opacity-100 translate-x-0"
        x-transition:leave-end="transform opacity-0 translate-x-full"
        class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-xl border px-4 py-3 shadow-xl"
        :class="type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : (type === 'info' ? 'bg-blue-50 border-blue-200 text-blue-800' : 'bg-red-50 border-red-200 text-red-800')"
        role="alert"
    >
        <svg
            class="h-5 w-5 shrink-0"
            :class="type === 'success' ? 'text-green-500' : (type === 'info' ? 'text-blue-500' : 'text-red-500')"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
        >
            <template x-if="type === 'success'">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4 4L19 7" />
            </template>
            <template x-if="type === 'info'">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </template>
            <template x-if="type === 'danger'">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v4.5m0 3h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </template>
        </svg>
        <p class="flex-1 text-sm font-medium" x-text="message"></p>
        <button
            type="button"
            @click="closeToast()"
            class="text-current opacity-50 transition-opacity hover:opacity-100"
            aria-label="Tutup"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" />
            </svg>
        </button>
    </div>
