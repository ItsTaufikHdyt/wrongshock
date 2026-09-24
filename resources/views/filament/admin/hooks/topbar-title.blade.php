@php
    $title = match (true) {
        request()->routeIs('filament.adminPanel.pages.admin-dashboard') => 'Dashboard',
        request()->routeIs('filament.adminPanel.resources.users.*') => 'Anggota',
        request()->routeIs('filament.adminPanel.resources.waste-deposits.*') => 'Setoran',
        request()->routeIs('filament.adminPanel.resources.withdrawals.*') => 'Penarikan',
        request()->routeIs('filament.adminPanel.resources.waste-items.*') => 'Jenis Sampah',
        request()->routeIs('filament.adminPanel.resources.districts.*') => 'Kecamatan',
        request()->routeIs('filament.adminPanel.resources.sub-districts.*') => 'Kelurahan',
        request()->routeIs('filament.adminPanel.resources.waste-deposit-items.*') => 'Rincian Setoran',
        request()->routeIs('filament.adminPanel.resources.waste-banks.*') => 'Bank Sampah',
        request()->routeIs('filament.adminPanel.resources.waste-bank-staff.*') => 'Admin Bank Sampah',
        default => 'Admin Panel',
    };
@endphp
<div class="ws-admin-topbar-title"><strong>{{ $title }}</strong><span>Kelola operasional Wrongshock</span></div>
