<div
    wire:ignore
    data-member-qr-scanner
    data-resolver-url="{{ route('admin.member.resolve-qr') }}"
>
    <div class="ws-member-scanner-entry">
        <span class="ws-member-scanner-or">atau</span>
        <button type="button" class="fi-btn fi-btn-color-primary fi-color-primary fi-size-md" data-scanner-open>
            <x-filament::icon icon="heroicon-o-qr-code" class="fi-btn-icon" />
            <span>Scan QR Anggota</span>
        </button>
    </div>

    <div class="ws-member-scanner-result" data-scanner-result hidden aria-live="polite"></div>

    <div class="ws-member-scanner-modal" data-scanner-modal hidden role="dialog" aria-modal="true" aria-labelledby="member-scanner-title">
        <div class="ws-member-scanner-card">
            <div class="ws-member-scanner-heading">
                <div>
                    <h2 id="member-scanner-title">Scan QR Anggota</h2>
                    <p>Arahkan kamera ke QR pada kartu anggota.</p>
                </div>
                <button type="button" class="ws-member-scanner-close" data-scanner-close aria-label="Tutup">&times;</button>
            </div>

            <div id="member-qr-reader" class="ws-member-scanner-camera" data-scanner-reader></div>
            <select class="ws-member-scanner-camera-select" data-scanner-camera hidden aria-label="Pilih kamera"></select>
            <p class="ws-member-scanner-status" data-scanner-status aria-live="polite">Kamera belum dimulai.</p>
            <div class="ws-member-scanner-actions">
                <button type="button" class="fi-btn fi-btn-color-gray fi-size-md" data-scanner-retry hidden>Coba Lagi</button>
                <button type="button" class="fi-btn fi-btn-color-gray fi-size-md" data-scanner-close>Tutup</button>
            </div>
        </div>
    </div>
</div>
