<x-filament::page>
    <div class="ws-page">
        <section class="ws-hero" aria-labelledby="dashboard-hero-title">
            <div class="ws-hero-copy">
                <span class="ws-eyebrow">Selamat datang kembali</span>
                <h1 id="dashboard-hero-title">Halo, {{ $user->name }}!</h1>
                <p>Yuk, terus berkontribusi untuk lingkungan yang lebih bersih dan sehat.</p>
                <span class="ws-member-pill">Anggota #{{ $user->number }}</span>
            </div>
            <img src="{{ asset('images/wrongshock/eco-community.svg') }}" alt="" class="ws-hero-illustration" aria-hidden="true">
        </section>

        <section class="ws-dashboard-grid" aria-label="Ringkasan akun">
            <article class="ws-balance-card" aria-labelledby="balance-heading">
                <div class="ws-balance-content">
                    <span class="ws-balance-icon" aria-hidden="true">
                        <x-filament::icon icon="heroicon-o-wallet" />
                    </span>
                    <div>
                        <p id="balance-heading">Saldo Tabungan</p>
                        <strong>{{ $this->formatRupiah($user->balance) }}</strong>
                        <span>Saldo tabungan sampah Anda saat ini</span>
                    </div>
                </div>
                <span class="ws-balance-leaf ws-balance-leaf-one" aria-hidden="true"></span>
                <span class="ws-balance-leaf ws-balance-leaf-two" aria-hidden="true"></span>
            </article>

            <div class="ws-quick-actions" aria-labelledby="quick-actions-heading">
                <div class="ws-section-heading ws-section-heading-compact">
                    <div>
                        <h2 id="quick-actions-heading">Akses Cepat</h2>
                        <p>Pilih tujuan Anda.</p>
                    </div>
                </div>
                <div class="ws-action-grid">
                    <a href="{{ $this->historyUrl() }}" class="ws-action-card">
                        <span class="ws-icon-box ws-icon-mint" aria-hidden="true"><x-filament::icon icon="heroicon-o-document-text" /></span>
                        <span><strong>Riwayat Setoran</strong><small>Lihat transaksi</small></span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="ws-action-arrow" aria-hidden="true" />
                    </a>
                    <a href="{{ $this->profileUrl() }}" class="ws-action-card">
                        <span class="ws-icon-box ws-icon-yellow" aria-hidden="true"><x-filament::icon icon="heroicon-o-user-circle" /></span>
                        <span><strong>Profil saya</strong><small>Kelola akun</small></span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="ws-action-arrow" aria-hidden="true" />
                    </a>
                </div>
            </div>
        </section>

        <section id="setoran-terbaru" class="ws-content-section" aria-labelledby="recent-deposits-heading">
            <div class="ws-section-heading">
                <div>
                    <h2 id="recent-deposits-heading">Setoran Terbaru</h2>
                    <p>Aktivitas setoran sampah Anda yang baru dicatat.</p>
                </div>
                <a href="{{ $this->historyUrl() }}" class="ws-text-link">Lihat semua <x-filament::icon icon="heroicon-m-arrow-right" aria-hidden="true" /></a>
            </div>

            <div class="ws-card-list">
                @forelse ($deposits as $deposit)
                    <article class="ws-transaction-card" aria-label="Setoran {{ $this->formatDate($deposit->deposit_date) }}">
                        <div class="ws-date-box">
                            <x-filament::icon icon="heroicon-o-calendar-days" aria-hidden="true" />
                            <time datetime="{{ $deposit->deposit_date->toDateString() }}">{{ $this->formatDate($deposit->deposit_date) }}</time>
                        </div>
                        <div class="ws-transaction-body">
                            <div class="ws-transaction-head">
                                <div>
                                    <h3>Setoran Sampah</h3>
                                <p><x-filament::icon icon="heroicon-o-cube" aria-hidden="true" /> {{ $deposit->items->count() }} item sampah</p>
                                <p><x-filament::icon icon="heroicon-o-building-storefront" aria-hidden="true" /> {{ $deposit->wasteBank?->name ?? 'Bank Sampah Utama' }}</p>
                                </div>
                                <span class="ws-status {{ $this->statusClasses($deposit->status) }}">{{ $this->statusLabel($deposit->status) }}</span>
                            </div>
                            <div class="ws-item-list">
                                @forelse ($deposit->items as $item)
                                    <div class="ws-item-row">
                                        <span class="ws-item-icon" aria-hidden="true"><x-filament::icon icon="heroicon-o-arrow-path-rounded-square" /></span>
                                        <div class="ws-item-copy">
                                            <strong>{{ $this->itemName($item) }}</strong>
                                            <span>
                                                {{ $this->formatQuantity($item->quantity) }} {{ $this->itemUnit($item) }}
                                                @if ($item->unit_price_snapshot !== null)
                                                    &times; {{ $this->formatRupiah($item->unit_price_snapshot) }} / {{ $this->itemUnit($item) }}
                                                @endif
                                            </span>
                                        </div>
                                        <strong class="ws-item-value">{{ $this->formatRupiah($item->subtotal) }}</strong>
                                    </div>
                                @empty
                                    <p class="ws-empty-copy">Detail item setoran belum tersedia.</p>
                                @endforelse
                            </div>
                            <div class="ws-transaction-total">
                                <span>Total Setoran</span>
                                <strong>{{ $this->formatRupiah($deposit->total_amount) }}</strong>
                            </div>
                            @if ($deposit->status === 'cancelled')
                                <div class="ws-cancelled-note">
                                    <x-filament::icon icon="heroicon-o-exclamation-triangle" aria-hidden="true" />
                                    <p><strong>Setoran ini telah dibatalkan.</strong>@if (filled($deposit->cancellation_reason)) <span>Alasan: {{ $deposit->cancellation_reason }}</span>@endif</p>
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="ws-empty-state">
                        <span class="ws-empty-icon" aria-hidden="true"><x-filament::icon icon="heroicon-o-document-text" /></span>
                        <h3>Belum ada setoran</h3>
                        <p>Riwayat setoran sampah Anda akan muncul di sini setelah transaksi dicatat oleh petugas.</p>
                    </div>
                @endforelse
            </div>
        </section>

        @include('filament.user-panel.partials.eco-banner')
    </div>
</x-filament::page>
