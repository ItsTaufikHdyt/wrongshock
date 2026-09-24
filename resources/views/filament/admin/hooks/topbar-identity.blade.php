@php
    $admin = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($admin?->name ?? '')))->filter()->take(2)->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $context = $admin?->isPlatformAdmin() ? 'Platform Admin' : ($admin?->wasteBanksAsStaff()->where('status', true)->value('name') ?? 'Admin Bank Sampah');
@endphp
@if ($admin)
    <div class="ws-admin-topbar-identity"><span class="ws-admin-avatar" aria-hidden="true">{{ $initials ?: 'A' }}</span><span><strong>{{ $admin->name }}</strong><small>{{ $context }}</small></span></div>
@endif
