<x-filament::page>
    <div class="ws-page">
        <a href="{{ \App\Filament\UserPanel\Pages\DepositHistory::getUrl(panel: 'userPanel') }}" class="ws-back-link">
            <x-filament::icon icon="heroicon-o-arrow-left" aria-hidden="true" /> Kembali ke Riwayat Setoran
        </a>

        <section class="ws-detail-hero" aria-labelledby="detail-title">
            <div>
                <span class="ws-eyebrow">Rincian transaksi</span>
                <h1 id="detail-title">Setoran Sampah</h1>
                <p><x-filament::icon icon="heroicon-o-calendar-days" aria-hidden="true" /> {{ $this->formatDate($deposit->deposit_date, true) }}</p>
            </div>
            <span class="ws-status {{ $this->statusClasses($deposit->status) }}">{{ $this->statusLabel($deposit->status) }}</span>
        </section>

        <section class="ws-detail-summary" aria-label="Ringkasan setoran">
            <div>
                <span class="ws-icon-box ws-icon-mint" aria-hidden="true"><x-filament::icon icon="heroicon-o-cube" /></span>
                <p><span>Jumlah Item</span><strong>{{ $deposit->items->count() }} item sampah</strong></p>
            </div>
            <div>
                <span class="ws-icon-box ws-icon-yellow" aria-hidden="true"><x-filament::icon icon="heroicon-o-banknotes" /></span>
                <p><span>Total Setoran</span><strong>{{ $this->formatRupiah($deposit->total_amount) }}</strong></p>
            </div>
        </section>

        <section class="ws-content-section" aria-labelledby="detail-items-heading">
            <div class="ws-section-heading">
                <div><h2 id="detail-items-heading">Rincian Item Setoran</h2><p>Material dan nilai yang tercatat pada transaksi ini.</p></div>
            </div>
            <div class="ws-item-list ws-detail-item-list">
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
            <div class="ws-detail-total"><span>Total Setoran</span><strong>{{ $this->formatRupiah($deposit->total_amount) }}</strong></div>
        </section>

        @if ($deposit->status === 'cancelled')
            <section class="ws-cancelled-note ws-cancelled-note-large" aria-labelledby="cancelled-heading">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" aria-hidden="true" />
                <div>
                    <h2 id="cancelled-heading">Setoran ini telah dibatalkan.</h2>
                    <p>Setoran ini telah dibatalkan dan tidak lagi diperhitungkan ke saldo Anda.</p>
                    @if (filled($deposit->cancellation_reason))
                        <p><strong>Alasan:</strong> {{ $deposit->cancellation_reason }}</p>
                    @endif
                </div>
            </section>
        @endif

        @include('filament.user-panel.partials.eco-banner')
    </div>
</x-filament::page>
