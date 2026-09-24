@php
    $admin = auth()->user();
    $bank = $admin?->isBankAdmin() ? app(\App\Services\WasteBankContext::class)->current() : null;
@endphp
@if ($bank)
    <div class="ws-admin-sidebar-context" aria-label="Bank sampah aktif">
        <span>BANK SAMPAH</span>
        <strong>{{ $bank->name }}</strong>
        <small>{{ $bank->code }}</small>
    </div>
@else
    <div class="ws-admin-sidebar-note"><x-filament::icon icon="heroicon-o-shield-check" /><p><strong>Platform Admin</strong><br>Kelola struktur dan data platform.</p></div>
@endif
