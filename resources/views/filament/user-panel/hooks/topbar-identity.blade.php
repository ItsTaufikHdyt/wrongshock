@php
    $user = auth()->user();
    $hasImage = filled($user?->image) && Storage::disk('public')->exists($user->image);
    $initials = collect(preg_split('/\s+/', trim($user?->name ?? '')))
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

@if ($user)
    <div class="ws-topbar-identity">
        @if ($hasImage)
            <img src="{{ Storage::disk('public')->url($user->image) }}" alt="Foto profil {{ $user->name }}" class="ws-topbar-avatar">
        @else
            <span class="ws-topbar-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
        @endif
        <div>
            <strong>{{ $user->name }}</strong>
            <span>Anggota #{{ $user->number }}</span>
        </div>
    </div>
@endif
