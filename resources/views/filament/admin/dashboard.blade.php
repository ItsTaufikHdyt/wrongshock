@php($data = $this->getDashboardData())

<x-filament-panels::page class="ws-admin-dashboard">
    <div class="ws-admin-page">
        <section class="ws-admin-welcome" aria-labelledby="admin-dashboard-title">
            <div>
                <span class="ws-admin-eyebrow">Ringkasan operasional</span>
                <h1 id="admin-dashboard-title">Selamat datang kembali, {{ $data['admin']?->name ?? 'Admin' }}</h1>
                <p>Pantau aktivitas bank sampah dan kelola operasional Wrongshock dari satu tempat.</p>
            </div>
            <div class="ws-admin-period" aria-label="Periode dashboard">
                <x-filament::icon icon="heroicon-o-calendar-days" />
                <span>Bulan ini<br><strong>{{ $data['period'] }}</strong></span>
            </div>
        </section>

        <section aria-labelledby="admin-kpi-title">
            <div class="ws-admin-section-heading">
                <div>
                    <h2 id="admin-kpi-title">Angka Utama</h2>
                    <p>Ringkasan aktual untuk membantu keputusan hari ini.</p>
                </div>
            </div>
            <div class="ws-admin-kpi-grid">
                <article class="ws-admin-kpi ws-admin-kpi-mint">
                    <span class="ws-admin-kpi-icon"><x-filament::icon icon="heroicon-o-users" /></span>
                    <span class="ws-admin-kpi-label">Total Anggota</span>
                    <strong>{{ number_format($data['members'], 0, ',', '.') }}</strong>
                    <small>{{ number_format($data['active_members'], 0, ',', '.') }} aktif</small>
                </article>
                <article class="ws-admin-kpi ws-admin-kpi-sky">
                    <span class="ws-admin-kpi-icon"><x-filament::icon icon="heroicon-o-arrow-trending-up" /></span>
                    <span class="ws-admin-kpi-label">Setoran Berhasil</span>
                    <strong>{{ number_format($data['monthly_deposit_count'], 0, ',', '.') }}</strong>
                    <small>Bulan ini</small>
                </article>
                <article class="ws-admin-kpi ws-admin-kpi-yellow">
                    <span class="ws-admin-kpi-icon"><x-filament::icon icon="heroicon-o-banknotes" /></span>
                    <span class="ws-admin-kpi-label">Nilai Setoran</span>
                    <strong>{{ $this->formatCurrency($data['monthly_deposit_amount']) }}</strong>
                    <small>Bulan ini</small>
                </article>
                <article class="ws-admin-kpi ws-admin-kpi-lilac">
                    <span class="ws-admin-kpi-icon"><x-filament::icon icon="heroicon-o-user-group" /></span>
                    <span class="ws-admin-kpi-label">Anggota Aktif Setor</span>
                    <strong>{{ number_format($data['active_depositors'], 0, ',', '.') }}</strong>
                    <small>Bulan ini</small>
                </article>
            </div>
        </section>

        <section class="ws-admin-dashboard-grid" aria-label="Analitik operasional">
            <article class="ws-admin-card ws-admin-trend-card">
                <div class="ws-admin-section-heading">
                    <div>
                        <h2>Aktivitas Setoran</h2>
                        <p>Nilai setoran berhasil dalam 6 bulan terakhir.</p>
                    </div>
                    <span class="ws-admin-card-note">Rupiah</span>
                </div>
                @if ($data['has_trend_data'])
                    <div class="ws-admin-chart" role="img" aria-label="Grafik nilai setoran berhasil enam bulan terakhir">
                        @php($maxTrend = max(1, max(array_column($data['trend'], 'amount'))))
                        @foreach ($data['trend'] as $month)
                            <div class="ws-admin-bar-group">
                                <span class="ws-admin-bar-value">{{ $this->formatCurrency($month['amount']) }}</span>
                                <div class="ws-admin-bar-track"><span style="height: {{ max(4, round(($month['amount'] / $maxTrend) * 100)) }}%"></span></div>
                                <span class="ws-admin-bar-label">{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="ws-admin-chart-summary">{{ number_format(collect($data['trend'])->sum('count'), 0, ',', '.') }} setoran berhasil pada periode ini.</p>
                @else
                    <div class="ws-admin-empty"><x-filament::icon icon="heroicon-o-chart-bar" /><p>Belum ada data setoran pada periode ini.</p></div>
                @endif
            </article>

            <article class="ws-admin-card">
                <div class="ws-admin-section-heading">
                    <div>
                        <h2>Ringkasan Anggota</h2>
                        <p>Status akun anggota saat ini.</p>
                    </div>
                    <x-filament::icon icon="heroicon-o-user-group" class="ws-admin-heading-icon" />
                </div>
                <div class="ws-admin-member-summary">
                    <div><span>Semua anggota</span><strong>{{ number_format($data['members'], 0, ',', '.') }}</strong></div>
                    <div><span>Aktif</span><strong class="ws-admin-positive">{{ number_format($data['active_members'], 0, ',', '.') }}</strong></div>
                    <div><span>Nonaktif</span><strong class="ws-admin-warning-text">{{ number_format($data['inactive_members'], 0, ',', '.') }}</strong></div>
                </div>
                <a class="ws-admin-link" href="{{ $data['links']['members'] }}">Kelola anggota <x-filament::icon icon="heroicon-m-arrow-right" /></a>
            </article>
        </section>

        <section class="ws-admin-dashboard-grid" aria-label="Rincian setoran">
            <article class="ws-admin-card">
                <div class="ws-admin-section-heading">
                    <div>
                        <h2>Komposisi Setoran</h2>
                        <p>Berdasarkan nilai setoran bulan ini, memakai kategori snapshot.</p>
                    </div>
                    <x-filament::icon icon="heroicon-o-chart-pie" class="ws-admin-heading-icon" />
                </div>
                @if ($data['has_composition_data'])
                    <div class="ws-admin-composition-list">
                        @php($compositionMax = max(1, max(array_column($data['composition'], 'amount'))))
                        @foreach ($data['composition'] as $item)
                            <div class="ws-admin-composition-row">
                                <div class="ws-admin-composition-head"><strong>{{ $item['category'] }}</strong><span>{{ $this->formatCurrency($item['amount']) }}</span></div>
                                <div class="ws-admin-composition-track"><span style="width: {{ max(3, round(($item['amount'] / $compositionMax) * 100)) }}%"></span></div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="ws-admin-empty"><x-filament::icon icon="heroicon-o-inbox" /><p>Belum ada komposisi setoran bulan ini.</p></div>
                @endif
            </article>

            <article class="ws-admin-card">
                <div class="ws-admin-section-heading">
                    <div>
                        <h2>Perlu Perhatian</h2>
                        <p>Ringkasan status yang dapat ditindaklanjuti.</p>
                    </div>
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="ws-admin-heading-icon ws-admin-attention-icon" />
                </div>
                <div class="ws-admin-attention-list">
                    @if ($data['inactive_members'] > 0)
                        <a href="{{ $data['links']['members'] }}" class="ws-admin-attention-item"><span class="ws-admin-attention-badge is-amber"><x-filament::icon icon="heroicon-o-user" /></span><span><strong>{{ number_format($data['inactive_members'], 0, ',', '.') }} akun anggota belum aktif</strong><small>Periksa dan kelola status anggota</small></span><x-filament::icon icon="heroicon-m-arrow-right" /></a>
                    @endif
                    @if ($data['pending_withdrawals'] > 0)
                        <a href="{{ $data['links']['withdrawals'] }}" class="ws-admin-attention-item"><span class="ws-admin-attention-badge is-red"><x-filament::icon icon="heroicon-o-banknotes" /></span><span><strong>{{ number_format($data['pending_withdrawals'], 0, ',', '.') }} penarikan menunggu proses</strong><small>Review pengajuan penarikan saldo</small></span><x-filament::icon icon="heroicon-m-arrow-right" /></a>
                    @endif
                    @if ($data['inactive_members'] === 0 && $data['pending_withdrawals'] === 0)
                        <div class="ws-admin-empty ws-admin-empty-compact"><x-filament::icon icon="heroicon-o-check-circle" /><p>Tidak ada hal yang perlu ditindaklanjuti.</p></div>
                    @endif
                </div>
            </article>
        </section>

        <section class="ws-admin-card" aria-labelledby="recent-deposits-title">
            <div class="ws-admin-section-heading">
                <div>
                    <h2 id="recent-deposits-title">Setoran Terbaru</h2>
                    <p>Enam transaksi terakhir dari seluruh periode.</p>
                </div>
                <a class="ws-admin-link" href="{{ $data['links']['deposits'] }}">Lihat semua setoran <x-filament::icon icon="heroicon-m-arrow-right" /></a>
            </div>
            @if ($data['recent_deposits']->isNotEmpty())
                <div class="ws-admin-table-wrap">
                    <table class="ws-admin-table">
                        <caption class="sr-only">Daftar setoran terbaru</caption>
                        <thead><tr><th scope="col">Tanggal</th><th scope="col">Anggota</th><th scope="col">Nomor Anggota</th><th scope="col">Total</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                            @foreach ($data['recent_deposits'] as $deposit)
                                <tr>
                                    <td>{{ $deposit->deposit_date?->format('d M Y') }}</td>
                                    <td>{{ $deposit->user?->name ?? 'Anggota dihapus' }}</td>
                                    <td>{{ $deposit->user?->number ?? '-' }}</td>
                                    <td class="ws-admin-money">{{ $this->formatCurrency((int) $deposit->total_amount) }}</td>
                                    <td><span class="ws-admin-status {{ $this->statusClass($deposit->status) }}">{{ $this->statusLabel($deposit->status) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="ws-admin-empty"><x-filament::icon icon="heroicon-o-inbox" /><p>Belum ada setoran.</p></div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
