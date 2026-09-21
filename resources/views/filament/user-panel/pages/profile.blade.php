<x-filament-panels::page>
    <div class="ws-profile-page">
        <div class="ws-profile-intro">
            <div>
                <span class="ws-eyebrow">Akun Wrongshock</span>
                <h1>Profil Saya</h1>
                <p>Kelola informasi akun dan data pribadi Anda.</p>
            </div>
            <span class="ws-profile-intro-leaf ws-profile-intro-leaf-one" aria-hidden="true"></span>
            <span class="ws-profile-intro-leaf ws-profile-intro-leaf-two" aria-hidden="true"></span>
        </div>

        <x-filament-panels::form id="profile-form" wire:submit="save">
            {{ $this->form }}

            <div class="ws-profile-actions">
                <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" />
            </div>
        </x-filament-panels::form>
    </div>
</x-filament-panels::page>
