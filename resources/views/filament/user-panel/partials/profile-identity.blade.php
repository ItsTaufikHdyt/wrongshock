@php
    $user = auth()->user();
    $hasImage = filled($user?->image) && Storage::disk('public')->exists($user->image);
    $initials = collect(preg_split('/\s+/', trim($user?->name ?? '')))
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<div class="ws-profile-identity">
    @if ($hasImage)
        <img src="{{ Storage::disk('public')->url($user->image) }}" alt="Foto profil {{ $user->name }}" class="ws-profile-avatar">
    @else
        <div class="ws-profile-avatar ws-profile-avatar-fallback" role="img" aria-label="Inisial {{ $user->name }}">
            {{ $initials ?: '?' }}
        </div>
    @endif
    <div>
        <h2>{{ $user->name }}</h2>
        <p>Anggota #{{ $user->number }}</p>
        <span>{{ $user->email }}</span>
    </div>
</div>
