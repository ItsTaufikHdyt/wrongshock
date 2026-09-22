@php
    $isAdmin = filament()->getId() === 'adminPanel';
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

<div class="ws-auth-shell {{ $isAdmin ? 'ws-auth-shell-admin' : 'ws-auth-shell-user' }}">
    <section class="ws-auth-hero" aria-labelledby="auth-hero-title">
        <div class="ws-auth-brand">
            <span class="ws-auth-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none">
                    <path d="M16 27C9.4 27 5 22.4 5 16.3 5 9.2 10.7 5 17.2 5c4.2 0 7.7 2.1 9.8 5.4-3.1-.7-6.5.1-8.9 2.3-2.8 2.6-3.6 6.5-2.5 9.9" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                    <path d="M15.7 22.6c1.7-5 5-8.8 10.2-11.5M10.4 17.9c1.5.2 3 .7 4.3 1.6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                </svg>
            </span>
            <span>
                <strong>Wrongshock</strong>
                <small>Bank Sampah Digital</small>
            </span>
        </div>

        <div class="ws-auth-hero-copy">
            @if ($isAdmin)
                <span class="ws-auth-hero-label">Operasional yang lebih tertata</span>
                <h2 id="auth-hero-title">Kelola Wrongshock<br>dengan Lebih Mudah</h2>
                <p>Area pengelola untuk mengelola anggota, transaksi, dan operasional bank sampah.</p>
            @else
                <span class="ws-auth-hero-label">Langkah kecil, dampak besar</span>
                <h2 id="auth-hero-title">Sampah Hari Ini,<br>Lingkungan Lebih Baik<br>Esok Nanti.</h2>
                <p>Mulai langkah kecil untuk lingkungan yang lebih bersih bersama Wrongshock.</p>
            @endif
        </div>

        <img
            src="{{ asset('images/wrongshock/eco-community.svg') }}"
            alt=""
            class="ws-auth-illustration"
            aria-hidden="true"
        >
        <span class="ws-auth-leaf ws-auth-leaf-one" aria-hidden="true"></span>
        <span class="ws-auth-leaf ws-auth-leaf-two" aria-hidden="true"></span>
    </section>

    <section class="ws-auth-form-panel" aria-labelledby="auth-title">
        <div class="ws-auth-card">
            <span class="ws-auth-context">
                @if ($isAdmin)
                    <x-filament::icon icon="heroicon-m-shield-check" aria-hidden="true" />
                    Area Pengelola
                @else
                    Wrongshock
                @endif
            </span>

            <div class="ws-auth-heading">
                <h1 id="auth-title">{{ $isAdmin ? 'Masuk sebagai Admin' : 'Selamat Datang!' }}</h1>
                <p>
                    {{ $isAdmin
                        ? 'Gunakan akun administrator Anda untuk melanjutkan.'
                        : 'Masuk untuk melihat saldo dan riwayat setoran Anda.' }}
                </p>
            </div>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

            <x-filament-panels::form id="form" wire:submit="authenticate" class="ws-auth-form">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </x-filament-panels::form>

            @if (! $isAdmin && \Illuminate\Support\Facades\Route::has('register'))
                <p class="ws-auth-register-link">Belum menjadi anggota? <a href="{{ route('register') }}">Daftar Sekarang</a></p>
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}

            @if ($isAdmin)
                <p class="ws-auth-security-note">
                    <x-filament::icon icon="heroicon-m-lock-closed" aria-hidden="true" />
                    Akses aman untuk pengelola Wrongshock.
                </p>
            @endif
        </div>
    </section>
</div>
