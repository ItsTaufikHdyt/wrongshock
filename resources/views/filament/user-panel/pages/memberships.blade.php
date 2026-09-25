<x-filament::page>
    <div class="ws-membership-page">
        <header class="ws-membership-header">
            <div class="ws-membership-header-icon" aria-hidden="true">
                <x-heroicon-o-building-storefront />
            </div>
            <div>
                <p class="ws-membership-eyebrow">Keanggotaan Saya</p>
                <h1>Keanggotaan Bank Sampah</h1>
                <p>Daftar Bank Sampah tempat Anda terdaftar sebagai anggota.</p>
            </div>
        </header>

        <div class="ws-membership-grid">
            @forelse ($this->memberships() as $membership)
                @php($isActive = $membership->status === 'active')
                <article class="ws-membership-card">
                    <div class="ws-membership-card-head">
                        <div class="ws-membership-bank-mark" aria-hidden="true">
                            <x-heroicon-o-arrow-path />
                        </div>
                        <div class="ws-membership-bank-copy">
                            <h2>{{ $membership->wasteBank->name }}</h2>
                            <p>{{ $membership->wasteBank->code }}</p>
                        </div>
                    </div>
                    <dl class="ws-membership-meta">
                        @if ($membership->wasteBank->district || $membership->wasteBank->subDistrict)
                            <div>
                                <dt>Wilayah</dt>
                                <dd>{{ $membership->wasteBank->district?->name ?: '-' }}{{ $membership->wasteBank->subDistrict ? ' • '.$membership->wasteBank->subDistrict->name : '' }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt>Status Keanggotaan</dt>
                            <dd class="ws-membership-status {{ $isActive ? 'is-active' : 'is-inactive' }}">
                                @if ($isActive)
                                    <x-heroicon-o-check-circle aria-hidden="true" />
                                @else
                                    <x-heroicon-o-minus-circle aria-hidden="true" />
                                @endif
                                <span>{{ $isActive ? 'Aktif' : 'Nonaktif' }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt>Bergabung</dt>
                            <dd>{{ $membership->joined_at?->translatedFormat('d F Y') ?: '-' }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <div class="ws-membership-empty">
                    <div class="ws-membership-empty-icon" aria-hidden="true">
                        <x-heroicon-o-building-storefront />
                    </div>
                    <h2>Belum Ada Keanggotaan</h2>
                    <p>Anda belum terdaftar sebagai anggota Bank Sampah.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament::page>
