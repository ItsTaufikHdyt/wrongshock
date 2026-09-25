<x-filament::page>
    @php($qrCode = $this->qrCode())

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-primary-600">Wrongshock</p>
            <h1 class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">Kartu Anggota</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Tunjukkan QR ini kepada petugas saat melakukan setoran.</p>
        </div>

        <section class="overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-8">
            <div class="flex flex-col items-center gap-6 text-center">
                <div>
                    <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ $this->user()->name }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Nomor Anggota</p>
                    <p class="font-mono text-base font-semibold text-gray-950 dark:text-white">{{ $this->user()->number }}</p>
                </div>

                @if ($qrCode)
                    <img class="h-64 w-64" src="{{ $qrCode }}" alt="QR identitas {{ $this->user()->name }}">
                @else
                    <p class="rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700">QR anggota belum tersedia. Silakan hubungi pengelola.</p>
                @endif
            </div>
        </section>
    </div>
</x-filament::page>
