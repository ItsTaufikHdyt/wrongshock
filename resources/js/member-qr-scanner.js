import axios from 'axios';

const STATES = {
    IDLE: 'IDLE',
    REQUESTING_PERMISSION: 'REQUESTING_PERMISSION',
    STARTING: 'STARTING',
    SCANNING: 'SCANNING',
    QR_DETECTED: 'QR_DETECTED',
    RESOLVING: 'RESOLVING',
    SUCCESS: 'SUCCESS',
    INVALID_QR: 'INVALID_QR',
    MEMBER_NOT_FOUND: 'MEMBER_NOT_FOUND',
    USER_INACTIVE: 'USER_INACTIVE',
    NOT_CITIZEN: 'NOT_CITIZEN',
    MEMBERSHIP_NOT_FOUND: 'MEMBERSHIP_NOT_FOUND',
    MEMBERSHIP_INACTIVE: 'MEMBERSHIP_INACTIVE',
    BANK_INACTIVE: 'BANK_INACTIVE',
    PERMISSION_DENIED: 'PERMISSION_DENIED',
    NO_CAMERA: 'NO_CAMERA',
    UNSUPPORTED: 'UNSUPPORTED',
    NETWORK_ERROR: 'NETWORK_ERROR',
    SCANNER_ERROR: 'SCANNER_ERROR',
    CAMERA_BUSY: 'CAMERA_BUSY',
    RATE_LIMITED: 'RATE_LIMITED',
    AUTHORIZATION_ERROR: 'AUTHORIZATION_ERROR',
    STOPPED: 'STOPPED',
};

const MESSAGES = {
    [STATES.IDLE]: 'Kamera belum dimulai.',
    [STATES.REQUESTING_PERMISSION]: 'Meminta izin kamera...',
    [STATES.STARTING]: 'Menyiapkan kamera...',
    [STATES.SCANNING]: 'Arahkan kamera ke QR anggota.',
    [STATES.RESOLVING]: 'Memeriksa anggota...',
    [STATES.SUCCESS]: 'Anggota ditemukan.',
    [STATES.INVALID_QR]: 'QR tidak valid.',
    [STATES.MEMBER_NOT_FOUND]: 'QR anggota tidak ditemukan.',
    [STATES.USER_INACTIVE]: 'Pengguna ini sedang tidak aktif.',
    [STATES.NOT_CITIZEN]: 'QR ini tidak dapat digunakan sebagai anggota.',
    [STATES.MEMBERSHIP_NOT_FOUND]: 'Anggota ditemukan, tetapi belum terdaftar pada Bank Sampah ini.',
    [STATES.MEMBERSHIP_INACTIVE]: 'Keanggotaan pada Bank Sampah ini tidak aktif.',
    [STATES.BANK_INACTIVE]: 'Bank Sampah saat ini tidak aktif.',
    [STATES.PERMISSION_DENIED]: 'Akses kamera tidak diizinkan.',
    [STATES.NO_CAMERA]: 'Kamera tidak tersedia pada perangkat ini.',
    [STATES.UNSUPPORTED]: 'Pemindai QR tidak didukung pada browser ini.',
    [STATES.NETWORK_ERROR]: 'Gagal memeriksa anggota. Periksa koneksi dan coba lagi.',
    [STATES.SCANNER_ERROR]: 'Pemindai kamera gagal dimulai. Coba lagi.',
    [STATES.CAMERA_BUSY]: 'Kamera tidak dapat digunakan. Pastikan kamera tidak sedang digunakan aplikasi lain, lalu coba lagi.',
    [STATES.RATE_LIMITED]: 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.',
    [STATES.AUTHORIZATION_ERROR]: 'Sesi admin tidak dapat memeriksa anggota. Muat ulang halaman jika diperlukan.',
    [STATES.STOPPED]: 'Pemindai ditutup.',
};

const ERROR_STATES = new Set([
    STATES.INVALID_QR,
    STATES.MEMBER_NOT_FOUND,
    STATES.USER_INACTIVE,
    STATES.NOT_CITIZEN,
    STATES.MEMBERSHIP_NOT_FOUND,
    STATES.MEMBERSHIP_INACTIVE,
    STATES.BANK_INACTIVE,
    STATES.PERMISSION_DENIED,
    STATES.NO_CAMERA,
    STATES.UNSUPPORTED,
    STATES.NETWORK_ERROR,
    STATES.SCANNER_ERROR,
    STATES.CAMERA_BUSY,
    STATES.RATE_LIMITED,
    STATES.AUTHORIZATION_ERROR,
]);

export class MemberQrScanner {
    constructor(root) {
        this.root = root;
        this.modal = root.querySelector('[data-scanner-modal]');
        this.reader = root.querySelector('[data-scanner-reader]');
        this.status = root.querySelector('[data-scanner-status]');
        this.retryButton = root.querySelector('[data-scanner-retry]');
        this.cameraSelect = root.querySelector('[data-scanner-camera]');
        this.result = root.querySelector('[data-scanner-result]');
        this.scanner = null;
        this.cameraStarted = false;
        this.cleanupPromise = Promise.resolve();
        this.abortController = null;
        this.session = 0;
        this.state = STATES.IDLE;

        this.cameraSelect?.addEventListener('change', () => {
            if (this.state === STATES.SCANNING) {
                this.restart(this.cameraSelect.value);
            }
        });
    }

    async open() {
        if (![STATES.IDLE, STATES.STOPPED, STATES.SUCCESS, ...ERROR_STATES].includes(this.state)) {
            return;
        }

        this.modal.hidden = false;
        this.setState(STATES.REQUESTING_PERMISSION);
        await this.cleanupPromise;
        this.session += 1;
        await this.start();
    }

    async start(cameraId = null) {
        if (![STATES.REQUESTING_PERMISSION, STATES.STARTING, STATES.STOPPED, ...ERROR_STATES].includes(this.state)) {
            return;
        }

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            this.setState(
                STATES.UNSUPPORTED,
                window.isSecureContext ? MESSAGES[STATES.UNSUPPORTED] : 'Kamera memerlukan koneksi HTTPS yang aman.',
            );
            return;
        }

        this.setState(STATES.STARTING);
        const session = this.session;

        try {
            if (!this.reader?.id || !document.body.contains(this.reader) || this.reader.offsetParent === null) {
                throw new Error('Scanner DOM target is missing or not visible.');
            }

            const { Html5Qrcode, Html5QrcodeSupportedFormats } = await import('html5-qrcode');
            const cameras = await Html5Qrcode.getCameras();

            if (session !== this.session || this.modal.hidden) {
                return;
            }

            if (!cameras.length) {
                this.setState(STATES.NO_CAMERA);
                return;
            }

            this.setCameraOptions(cameras);
            const rearCamera = cameras.find((camera) => /back|rear|environment|belakang/i.test(camera.label));
            const selectedCamera = cameraId || rearCamera?.id || { facingMode: 'environment' };
            this.scanner = new Html5Qrcode(this.reader.id, {
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
            });

            try {
                await this.startScanner(selectedCamera);
            } catch (error) {
                if (cameraId || rearCamera || this.isPermissionError(error)) {
                    throw error;
                }

                await this.stopScanner();
                this.scanner = new Html5Qrcode(this.reader.id, {
                    formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                });
                await this.startScanner(cameras[0].id);
            }

            if (session !== this.session || this.modal.hidden) {
                await this.stopScanner();
                return;
            }

            this.cameraStarted = true;
            this.setState(STATES.SCANNING);
        } catch (error) {
            if (session !== this.session || this.modal.hidden) {
                return;
            }

            this.logStartupFailure(error);
            await this.stopScanner();
            this.setState(
                this.isPermissionError(error)
                    ? STATES.PERMISSION_DENIED
                    : this.isNoCameraError(error)
                        ? STATES.NO_CAMERA
                        : this.isCameraBusyError(error) ? STATES.CAMERA_BUSY : STATES.SCANNER_ERROR,
            );
        }
    }

    startScanner(camera) {
        return this.scanner.start(
            camera,
            { fps: 10, qrbox: (width, height) => this.qrBox(width, height), aspectRatio: 1 },
            (payload) => this.handleDecode(payload),
            () => {},
        );
    }

    async restart(cameraId) {
        await this.stopScanner();
        if (this.modal.hidden) {
            return;
        }

        this.setState(STATES.STARTING);
        await this.start(cameraId);
    }

    async handleDecode(payload) {
        if (this.state !== STATES.SCANNING) {
            return;
        }

        const session = this.session;
        this.setState(STATES.QR_DETECTED);
        await this.stopScanner();

        if (session !== this.session || this.modal.hidden) {
            return;
        }

        this.setState(STATES.RESOLVING);
        this.abortController = new AbortController();

        try {
            const response = await axios.post(
                this.root.dataset.resolverUrl,
                { payload },
                { signal: this.abortController.signal },
            );

            if (session !== this.session || this.state !== STATES.RESOLVING) {
                return;
            }

            const member = response.data.member;
            if (!this.confirmReplacement(member.id)) {
                this.setState(STATES.STOPPED);
                return;
            }

            const wire = this.livewireComponent();
            if (!wire) {
                this.setState(STATES.SCANNER_ERROR);
                return;
            }

            await wire.set('data.user_id', member.id);
            this.result.textContent = `Anggota ditemukan: ${member.name} | ${member.number}`;
            this.result.hidden = false;
            this.setState(STATES.SUCCESS);
            this.close(false);
        } catch (error) {
            if (error.name === 'CanceledError' || error.name === 'AbortError') {
                return;
            }

            if (session !== this.session) {
                return;
            }

            this.setState(this.errorState(error));
        } finally {
            this.abortController = null;
        }
    }

    async close(invalidate = true) {
        if (invalidate) {
            this.session += 1;
            this.abortController?.abort();
        }

        this.modal.hidden = true;
        this.cleanupPromise = this.stopScanner();
        await this.cleanupPromise;

        if (this.state !== STATES.SUCCESS) {
            this.setState(STATES.STOPPED);
        }
    }

    async stopScanner() {
        const scanner = this.scanner;
        this.scanner = null;
        this.cameraStarted = false;

        if (!scanner) {
            return;
        }

        try {
            if (scanner.getState?.() === 2 || scanner.getState?.() === 3) {
                await scanner.stop();
            }
        } catch {
            // The stream is still cleared below when the library exposes it.
        }

        try {
            await scanner.clear();
        } catch {
            // clear() can reject after a browser has already stopped the stream.
        }
    }

    setState(state, message = null) {
        this.state = state;
        this.status.textContent = message || MESSAGES[state] || MESSAGES[STATES.SCANNER_ERROR];
        this.retryButton.hidden = !ERROR_STATES.has(state);
    }

    setCameraOptions(cameras) {
        this.cameraSelect.replaceChildren();
        cameras.forEach((camera) => {
            const option = new Option(camera.label || `Kamera ${this.cameraSelect.length + 1}`, camera.id);
            this.cameraSelect.add(option);
        });
        this.cameraSelect.value = this.preferredCamera(cameras).id;
        this.cameraSelect.hidden = cameras.length < 2;
    }

    preferredCamera(cameras) {
        return cameras.find((camera) => /back|rear|environment|belakang/i.test(camera.label)) || cameras[0];
    }

    qrBox(width, height) {
        const size = Math.min(width, height, 280);
        return { width: Math.max(size, 160), height: Math.max(size, 160) };
    }

    confirmReplacement(id) {
        const selected = this.livewireComponent()?.el?.querySelector('[name="data[user_id]"]')?.value;
        return !selected || String(selected) === String(id) || window.confirm('Ganti anggota yang sedang dipilih?');
    }

    livewireComponent() {
        let element = this.root;
        while (element && !element.getAttribute('wire:id')) {
            element = element.parentElement;
        }

        const id = element?.getAttribute('wire:id');
        return id && window.Livewire?.find(id);
    }

    errorState(error) {
        const status = error.response?.status;
        if (status === 401 || status === 403) {
            return STATES.AUTHORIZATION_ERROR;
        }
        if (status === 429) {
            return STATES.RATE_LIMITED;
        }
        if (status === 422) {
            return error.response.data?.status || STATES.INVALID_QR;
        }
        return error.response ? STATES.SCANNER_ERROR : STATES.NETWORK_ERROR;
    }

    isPermissionError(error) {
        return error?.name === 'NotAllowedError' || /permission|not allowed/i.test(error?.message || '');
    }

    isNoCameraError(error) {
        return error?.name === 'NotFoundError' || error?.name === 'DevicesNotFoundError';
    }

    isCameraBusyError(error) {
        return ['NotReadableError', 'TrackStartError', 'AbortError', 'OverconstrainedError'].includes(error?.name);
    }

    logStartupFailure(error) {
        if (!import.meta.env.DEV && !['localhost', '127.0.0.1', '[::1]'].includes(location.hostname)) {
            return;
        }

        console.error('[MemberQrScanner] Camera startup failed', {
            name: error?.name,
            message: error?.message,
            stack: error?.stack,
            secureContext: window.isSecureContext === true,
            mediaDevices: typeof navigator.mediaDevices,
            getUserMedia: typeof navigator.mediaDevices?.getUserMedia,
            protocol: location.protocol,
            hostname: location.hostname,
            readerId: this.reader?.id || null,
            readerMounted: Boolean(this.reader && document.body.contains(this.reader)),
            readerVisible: Boolean(this.reader && this.reader.offsetParent !== null),
        });
    }
}

const scanners = new WeakMap();

function scannerFor(root) {
    if (!scanners.has(root)) {
        scanners.set(root, new MemberQrScanner(root));
    }

    return scanners.get(root);
}

document.addEventListener('click', (event) => {
    const open = event.target.closest?.('[data-scanner-open]');
    const close = event.target.closest?.('[data-scanner-close]');
    const retry = event.target.closest?.('[data-scanner-retry]');
    const root = event.target.closest?.('[data-member-qr-scanner]');

    if (!root) {
        return;
    }

    const scanner = scannerFor(root);
    if (open) {
        scanner.open();
    } else if (close) {
        scanner.close();
    } else if (retry) {
        scanner.open();
    }
});

document.addEventListener('livewire:navigating', () => {
    document.querySelectorAll('[data-member-qr-scanner]').forEach((root) => {
        scannerFor(root).close();
    });
});
