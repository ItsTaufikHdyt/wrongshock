<aside class="ws-eco-banner" aria-label="Pesan lingkungan">
    <div class="ws-eco-banner-copy">
        <span class="ws-eco-banner-icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-sparkles" />
        </span>
        <div>
            <h2>Teruslah menjadi bagian dari perubahan!</h2>
            <p>Setiap setoran yang Anda lakukan sangat berarti untuk lingkungan yang lebih bersih dan sehat.</p>
        </div>
    </div>
    @if (! request()->is('user/user-dashboard'))
        <a href="{{ \App\Filament\UserPanel\Pages\UserDashboard::getUrl(panel: 'userPanel') }}" class="ws-primary-button">
            Kembali ke Beranda
            <x-filament::icon icon="heroicon-m-arrow-right" aria-hidden="true" />
        </a>
    @endif
    <span class="ws-eco-banner-leaf ws-eco-banner-leaf-one" aria-hidden="true"></span>
    <span class="ws-eco-banner-leaf ws-eco-banner-leaf-two" aria-hidden="true"></span>
</aside>
