@php
    $isDetail = request()->is('user/setoran/*');
    $isHistory = request()->is('user/setoran') || $isDetail;
    $isProfile = request()->is('user/profile') || request()->is('user/users*');
    $title = $isDetail ? 'Detail Setoran' : ($isHistory ? 'Riwayat Setoran' : ($isProfile ? 'Profil Saya' : 'Beranda'));
    $subtitle = $isDetail ? 'Rincian transaksi Anda' : ($isHistory ? 'Catatan kontribusi Anda' : ($isProfile ? 'Kelola informasi akun dan data pribadi Anda.' : 'Ringkasan aktivitas Anda'));
@endphp

<div class="ws-topbar-title">
    <strong>{{ $title }}</strong>
    <span>{{ $subtitle }}</span>
</div>
