<x-filament::page>
    <div class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-primary-600">Keanggotaan Saya</p>
            <h1 class="text-2xl font-bold text-gray-950 dark:text-white">Keanggotaan Bank Sampah</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Daftar bank sampah tempat Anda terdaftar.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($this->memberships() as $membership)
                <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-semibold text-gray-950 dark:text-white">{{ $membership->wasteBank->name }}</h2>
                            <p class="text-sm text-gray-500">{{ $membership->wasteBank->code }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $membership->status === 'active' ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $membership->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    <dl class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <div><dt class="inline font-medium">Alamat:</dt> <dd class="inline">{{ $membership->wasteBank->address ?: '-' }}</dd></div>
                        <div><dt class="inline font-medium">Bergabung:</dt> <dd class="inline">{{ $membership->joined_at?->translatedFormat('d F Y') ?: '-' }}</dd></div>
                    </dl>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400 md:col-span-2">
                    Anda belum terdaftar sebagai anggota Bank Sampah.
                </div>
            @endforelse
        </div>
    </div>
</x-filament::page>
