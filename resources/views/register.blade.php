<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Daftar sebagai anggota Wrongshock untuk memantau saldo, riwayat setoran, dan informasi harga sampah.">
    <meta name="theme-color" content="#176B4A">
    <title>Daftar Anggota — Wrongshock</title>
    <link rel="stylesheet" href="{{ asset('css/public-register.css') }}">
</head>
<body class="ws-register-page">
    <a href="#register-form" class="ws-register-skip-link">Lewati ke formulir pendaftaran</a>

    <main class="ws-register-shell">
        <section class="ws-register-hero" aria-labelledby="register-hero-title">
            <a href="{{ route('home') }}" class="ws-register-brand" aria-label="Kembali ke Beranda Wrongshock">
                <span class="ws-register-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M16 27C9.4 27 5 22.4 5 16.3 5 9.2 10.7 5 17.2 5c4.2 0 7.7 2.1 9.8 5.4-3.1-.7-6.5.1-8.9 2.3-2.8 2.6-3.6 6.5-2.5 9.9" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="M15.7 22.6c1.7-5 5-8.8 10.2-11.5M10.4 17.9c1.5.2 3 .7 4.3 1.6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                    </svg>
                </span>
                <span><strong>Wrongshock</strong><small>Bank Sampah Digital</small></span>
            </a>

            <div class="ws-register-hero-copy">
                <span class="ws-register-eyebrow">Gabung bersama komunitas</span>
                <h1 id="register-hero-title">Mulai dari Sampah,<br><span>Buat Perubahan.</span></h1>
                <p>Bergabung bersama Wrongshock dan kelola setoran sampahmu dengan lebih mudah.</p>
            </div>

            <div class="ws-register-illustration">
                <span class="ws-register-sun" aria-hidden="true"></span>
                <img src="{{ asset('images/wrongshock/eco-community.svg') }}" alt="Ilustrasi bumi, pepohonan, dan simbol daur ulang Wrongshock">
                <span class="ws-register-leaf ws-register-leaf-one" aria-hidden="true"></span>
                <span class="ws-register-leaf ws-register-leaf-two" aria-hidden="true"></span>
            </div>

            <div class="ws-register-benefits" aria-label="Manfaat akun anggota">
                <span><b aria-hidden="true">✓</b> Pantau saldo</span>
                <span><b aria-hidden="true">✓</b> Lihat riwayat setoran</span>
                <span><b aria-hidden="true">✓</b> Informasi harga sampah</span>
            </div>
        </section>

        <section class="ws-register-form-panel" aria-labelledby="register-title">
            <div class="ws-register-card">
                <a href="{{ route('home') }}" class="ws-register-back-link">&larr; Kembali ke Beranda</a>

                @if (session('success'))
                    <div class="ws-register-success" role="status" tabindex="-1">
                        <span aria-hidden="true">✓</span>
                        <div><strong>Pendaftaran berhasil.</strong><p>Akun Anda menunggu aktivasi dari pengelola sebelum dapat digunakan.</p></div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="ws-register-error-summary" role="alert" tabindex="-1">
                        <strong>Periksa kembali beberapa data berikut.</strong>
                        <span>{{ $errors->count() }} bagian perlu diperbaiki.</span>
                    </div>
                @endif

                <div class="ws-register-heading">
                    <span class="ws-register-context">Gabung Wrongshock</span>
                    <h2 id="register-title">Buat Akun Anggota</h2>
                    <p>Lengkapi data berikut untuk mendaftar sebagai anggota.</p>
                </div>

                <form id="register-form" action="{{ route('user.register') }}" method="POST" novalidate>
                    @csrf

                    <fieldset class="ws-register-group">
                        <legend>Informasi Pribadi</legend>
                        <div class="ws-register-field-grid">
                            <div class="ws-register-field ws-register-field-full">
                                <label for="name">Nama Lengkap <span aria-hidden="true">*</span></label>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" placeholder="Nama lengkap Anda" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="name-error">
                                @error('name')<p id="name-error" class="ws-register-field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="ws-register-field ws-register-field-full">
                                <label for="email">Email <span aria-hidden="true">*</span></label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="nama@email.com" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-error">
                                @error('email')<p id="email-error" class="ws-register-field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="ws-register-group">
                        <legend>Lokasi</legend>
                        <div class="ws-register-field-grid">
                            <div class="ws-register-field">
                                <label for="district">Kecamatan <span aria-hidden="true">*</span></label>
                                <select id="district" name="district" required data-old-value="{{ old('district') }}" aria-invalid="{{ $errors->has('district') ? 'true' : 'false' }}" aria-describedby="district-help district-error">
                                    <option value="">Memuat kecamatan...</option>
                                </select>
                                <p id="district-help" class="ws-register-field-help">Pilih kecamatan terlebih dahulu.</p>
                                @error('district')<p id="district-error" class="ws-register-field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="ws-register-field">
                                <label for="sub_district">Kelurahan <span aria-hidden="true">*</span></label>
                                <select id="sub_district" name="sub_district" required data-old-value="{{ old('sub_district') }}" disabled aria-invalid="{{ $errors->has('sub_district') ? 'true' : 'false' }}" aria-describedby="sub-district-help sub-district-error">
                                    <option value="">Pilih kecamatan terlebih dahulu</option>
                                </select>
                                <p id="sub-district-help" class="ws-register-field-help" aria-live="polite">Pilih kecamatan terlebih dahulu.</p>
                                @error('sub_district')<p id="sub-district-error" class="ws-register-field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="ws-register-field ws-register-field-full">
                                <label for="address">Alamat Lengkap <span aria-hidden="true">*</span></label>
                                <textarea id="address" name="address" rows="3" autocomplete="street-address" placeholder="Alamat tempat tinggal" required aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" aria-describedby="address-error">{{ old('address') }}</textarea>
                                @error('address')<p id="address-error" class="ws-register-field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="ws-register-group">
                        <legend>Keamanan Akun</legend>
                        <div class="ws-register-field-grid">
                            <div class="ws-register-field">
                                <label for="password">Password <span aria-hidden="true">*</span></label>
                                <div class="ws-register-password-wrap">
                                    <input id="password" name="password" type="password" autocomplete="new-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="password-help password-error">
                                    <button type="button" class="ws-register-password-toggle" data-password-target="password" aria-label="Tampilkan password" aria-pressed="false">Lihat</button>
                                </div>
                                <p id="password-help" class="ws-register-field-help">Gunakan minimal 8 karakter.</p>
                                @error('password')<p id="password-error" class="ws-register-field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="ws-register-field">
                                <label for="password_confirmation">Konfirmasi Password <span aria-hidden="true">*</span></label>
                                <div class="ws-register-password-wrap">
                                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}" aria-describedby="password-confirmation-error">
                                    <button type="button" class="ws-register-password-toggle" data-password-target="password_confirmation" aria-label="Tampilkan password" aria-pressed="false">Lihat</button>
                                </div>
                                @error('password_confirmation')<p id="password-confirmation-error" class="ws-register-field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>

                    <button type="submit" class="ws-register-submit">Daftar Sekarang</button>
                </form>

                <p class="ws-register-login-prompt">Sudah punya akun? <a href="{{ url('/user/login') }}">Masuk</a></p>
            </div>
        </section>
    </main>

    <script>
        const district = document.querySelector('#district');
        const subDistrict = document.querySelector('#sub_district');
        const subDistrictHelp = document.querySelector('#sub-district-help');

        const setOptions = (select, options, placeholder) => {
            select.replaceChildren(new Option(placeholder, ''));
            Object.entries(options).forEach(([id, name]) => select.append(new Option(name, id)));
        };

        const loadSubDistricts = async (districtId, selected = '') => {
            subDistrict.disabled = true;
            subDistrictHelp.textContent = districtId ? 'Memuat kelurahan...' : 'Pilih kecamatan terlebih dahulu.';
            setOptions(subDistrict, {}, districtId ? 'Memuat kelurahan...' : 'Pilih kecamatan terlebih dahulu');

            if (!districtId) return;

            try {
                const response = await fetch(`{{ url('/api/subdistricts') }}?district_id=${encodeURIComponent(districtId)}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('location request failed');
                const options = await response.json();
                setOptions(subDistrict, options, 'Pilih kelurahan');
                if (selected && options[selected]) subDistrict.value = selected;
                subDistrict.disabled = false;
                subDistrictHelp.textContent = 'Pilih kelurahan sesuai alamat Anda.';
            } catch {
                setOptions(subDistrict, {}, 'Kelurahan belum dapat dimuat');
                subDistrictHelp.textContent = 'Kelurahan belum dapat dimuat. Coba lagi nanti.';
            }
        };

        const loadDistricts = async () => {
            try {
                const response = await fetch('{{ url('/api/districts') }}', { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('district request failed');
                const options = await response.json();
                setOptions(district, options, 'Pilih kecamatan');
                const selected = district.dataset.oldValue;
                if (selected && options[selected]) {
                    district.value = selected;
                    await loadSubDistricts(selected, subDistrict.dataset.oldValue);
                }
            } catch {
                setOptions(district, {}, 'Kecamatan belum dapat dimuat');
                district.setAttribute('aria-describedby', 'district-help district-error');
                document.querySelector('#district-help').textContent = 'Kecamatan belum dapat dimuat. Coba lagi nanti.';
            }
        };

        district.addEventListener('change', () => loadSubDistricts(district.value));
        loadDistricts();

        document.querySelectorAll('.ws-register-password-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordTarget);
                const visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                button.textContent = visible ? 'Lihat' : 'Sembunyikan';
                button.setAttribute('aria-label', visible ? 'Tampilkan password' : 'Sembunyikan password');
                button.setAttribute('aria-pressed', String(!visible));
            });
        });
    </script>
</body>
</html>
