<x-filament::page>
    @php
        $deposits = $this->deposits();
        $summary = $this->summary();
    @endphp

    <div class="ws-page">
        <section class="ws-hero" aria-labelledby="history-hero-title">
            <div class="ws-hero-copy">
                <span class="ws-eyebrow">Kontribusi untuk lingkungan</span>
                <h1 id="history-hero-title">Setiap Setoran<br>Membawa Perubahan</h1>
                <p>Terima kasih telah berkontribusi dalam menjaga lingkungan bersama Wrongshock.</p>
            </div>
            <img src="{{ asset('images/wrongshock/eco-community.svg') }}" alt="" class="ws-hero-illustration" aria-hidden="true">
        </section>

        <section class="ws-summary-grid" aria-label="Ringkasan riwayat setoran">
            <div class="ws-summary-card">
                <span class="ws-icon-box ws-icon-mint" aria-hidden="true"><x-filament::icon icon="heroicon-o-check-circle" /></span>
                <div><p>Total Setoran</p><strong>{{ number_format($summary['posted_count'], 0, ',', '.') }}</strong><span>transaksi berhasil</span></div>
            </div>
            <div class="ws-summary-card">
                <span class="ws-icon-box ws-icon-yellow" aria-hidden="true"><x-filament::icon icon="heroicon-o-banknotes" /></span>
                <div><p>Total Nilai Setoran</p><strong>{{ $this->formatRupiah($summary['posted_value']) }}</strong><span>dari setoran berhasil</span></div>
            </div>
            <div class="ws-summary-card">
                <span class="ws-icon-box ws-icon-rose" aria-hidden="true"><x-filament::icon icon="heroicon-o-x-circle" /></span>
                <div><p>Setoran Dibatalkan</p><strong>{{ number_format($summary['cancelled_count'], 0, ',', '.') }}</strong><span>transaksi</span></div>
            </div>
        </section>

        <section class="ws-content-section" aria-labelledby="deposit-list-heading">
            <div class="ws-section-heading ws-history-heading">
                <div>
                    <h2 id="deposit-list-heading">Daftar Riwayat Setoran</h2>
                    <p>Berikut adalah riwayat setoran sampah Anda.</p>
                </div>
                <div role="group" aria-label="Filter status setoran" class="ws-filter-group">
                    @foreach ($this->filterOptions() as $value => $label)
                        <button type="button" wire:click="setStatus('{{ $value }}')" wire:loading.attr="disabled"
                            wire:target="setStatus"
                            aria-pressed="{{ $this->activeStatus() === $value ? 'true' : 'false' }}"
                            class="ws-filter {{ $this->activeStatus() === $value ? 'is-active' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                    <span class="sr-only" role="status" wire:loading wire:target="setStatus">Memuat riwayat setoran.</span>
                </div>
            </div>

            <div class="ws-card-list" wire:loading.attr="aria-busy" wire:target="setStatus">
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
                                </div>
                                <div class="ws-transaction-actions">
                                    <span class="ws-status {{ $this->statusClasses($deposit->status) }}">{{ $this->statusLabel($deposit->status) }}</span>
                                    <a href="{{ $this->detailUrl($deposit) }}" class="ws-detail-button">Lihat detail <x-filament::icon icon="heroicon-m-chevron-right" aria-hidden="true" /></a>
                                </div>
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
                                                    &times; {{ $this->formatRupiah($item->unit_price_snapshot) }}
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
                        @if ($this->activeStatus() === 'all')
                            <h3>Belum ada setoran</h3>
                            <p>Riwayat setoran sampah Anda akan muncul di sini setelah transaksi dicatat oleh petugas.</p>
                        @else
                            <h3>Tidak ada setoran dengan status ini.</h3>
                            <button type="button" wire:click="setStatus('all')" class="ws-secondary-button">Lihat semua setoran</button>
                        @endif
                    </div>
                @endforelse
            </div>

            @if ($deposits->hasPages())
                <div class="ws-pagination">{{ $deposits->links(data: ['scrollTo' => '#deposit-list-heading']) }}</div>
            @endif
        </section>

        @include('filament.user-panel.partials.eco-banner')
    </div>
</x-filament::page>
