<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Wrongshock adalah platform bank sampah digital untuk informasi harga, pencatatan setoran, saldo anggota, dan riwayat transaksi yang lebih terorganisir.">
    <meta name="theme-color" content="#176B4A">
    <title>Wrongshock — Bank Sampah Digital</title>
    <link rel="stylesheet" href="{{ asset('css/public-home.css') }}">
</head>
<body class="ws-public-page">
    <a href="#main-content" class="ws-public-skip-link">Lewati ke konten utama</a>

    <header class="ws-public-header">
        <nav class="ws-public-nav ws-public-container" aria-label="Navigasi utama">
            <a href="#beranda" class="ws-public-brand" aria-label="Wrongshock, kembali ke Beranda">
                <span class="ws-public-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M16 27C9.4 27 5 22.4 5 16.3 5 9.2 10.7 5 17.2 5c4.2 0 7.7 2.1 9.8 5.4-3.1-.7-6.5.1-8.9 2.3-2.8 2.6-3.6 6.5-2.5 9.9" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="M15.7 22.6c1.7-5 5-8.8 10.2-11.5M10.4 17.9c1.5.2 3 .7 4.3 1.6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                    </svg>
                </span>
                <span><strong>Wrongshock</strong><small>Bank Sampah Digital</small></span>
            </a>

            <div class="ws-public-nav-desktop">
                <div class="ws-public-nav-links">
                    <a href="#beranda">Beranda</a>
                    <a href="#cara-kerja">Cara Kerja</a>
                    <a href="#harga-sampah">Harga Sampah</a>
                    <a href="#informasi">Informasi</a>
                    <a href="#tentang">Tentang</a>
                </div>
                <div class="ws-public-nav-actions">
                    @if ($memberDashboardUrl)
                        <a href="{{ $memberDashboardUrl }}" class="ws-public-button ws-public-button-primary">Buka Beranda</a>
                    @elseif (! auth()->check())
                        <a href="{{ $loginUrl }}" class="ws-public-login-link">Masuk</a>
                        @if ($registrationUrl)
                            <a href="{{ $registrationUrl }}" class="ws-public-button ws-public-button-primary">Daftar Sekarang</a>
                        @endif
                    @endif
                </div>
            </div>

            <details class="ws-public-mobile-menu">
                <summary aria-label="Buka menu navigasi"><span></span><span></span><span></span></summary>
                <div class="ws-public-mobile-menu-panel">
                    <a href="#beranda">Beranda</a>
                    <a href="#cara-kerja">Cara Kerja</a>
                    <a href="#harga-sampah">Harga Sampah</a>
                    <a href="#informasi">Informasi</a>
                    <a href="#tentang">Tentang</a>
                    @if ($memberDashboardUrl)
                        <a href="{{ $memberDashboardUrl }}" class="ws-public-mobile-primary">Buka Beranda</a>
                    @elseif (! auth()->check())
                        <a href="{{ $loginUrl }}">Masuk</a>
                        @if ($registrationUrl)
                            <a href="{{ $registrationUrl }}" class="ws-public-mobile-primary">Daftar Sekarang</a>
                        @endif
                    @endif
                </div>
            </details>
        </nav>
    </header>

    <main id="main-content">
        <section id="beranda" class="ws-public-hero">
            <div class="ws-public-container ws-public-hero-grid">
                <div class="ws-public-hero-copy">
                    <span class="ws-public-eyebrow">Bersama untuk lingkungan lebih baik</span>
                    <h1>Ubah Sampah<br>Jadi <span>Lebih Bernilai</span></h1>
                    <p>Kelola sampah dengan lebih mudah dan jadikan setiap setoran sebagai langkah kecil menuju lingkungan yang lebih bersih.</p>
                    <div class="ws-public-hero-actions">
                        @if ($memberDashboardUrl)
                            <a href="{{ $memberDashboardUrl }}" class="ws-public-button ws-public-button-primary">Buka Beranda</a>
                        @elseif ($registrationUrl && ! auth()->check())
                            <a href="{{ $registrationUrl }}" class="ws-public-button ws-public-button-primary">Mulai Sekarang</a>
                        @endif
                        <a href="#harga-sampah" class="ws-public-button ws-public-button-secondary">Lihat Harga Sampah</a>
                        @if (! auth()->check())
                            <a href="{{ $loginUrl }}" class="ws-public-text-link">Sudah menjadi anggota? Masuk</a>
                        @endif
                    </div>
                </div>

                <div class="ws-public-hero-visual">
                    <span class="ws-public-sun" aria-hidden="true"></span>
                    <span class="ws-public-cloud ws-public-cloud-one" aria-hidden="true"></span>
                    <span class="ws-public-cloud ws-public-cloud-two" aria-hidden="true"></span>
                    <img src="{{ asset('images/wrongshock/eco-community.svg') }}" alt="Ilustrasi bumi, pepohonan, dan simbol daur ulang Wrongshock">
                    <span class="ws-public-leaf ws-public-leaf-one" aria-hidden="true"></span>
                    <span class="ws-public-leaf ws-public-leaf-two" aria-hidden="true"></span>
                </div>
            </div>
        </section>

        <section class="ws-public-values" aria-labelledby="values-title">
            <div class="ws-public-container">
                <h2 id="values-title" class="ws-public-visually-hidden">Manfaat Wrongshock</h2>
                <div class="ws-public-value-grid">
                    <article class="ws-public-value-card">
                        <span class="ws-public-icon ws-public-icon-mint" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M8 6h11M8 12h11M8 18h7M4 6h.01M4 12h.01M4 18h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                        <div><h3>Mudah Digunakan</h3><p>Pantau saldo dan riwayat setoran dalam satu tempat.</p></div>
                    </article>
                    <article class="ws-public-value-card">
                        <span class="ws-public-icon ws-public-icon-yellow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16M7 3v4m10-4v4M6 11h4v4H6zM4 5h16v16H4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
                        <div><h3>Transparan</h3><p>Nilai setoran dicatat berdasarkan jenis, jumlah, dan harga sampah.</p></div>
                    </article>
                    <article class="ws-public-value-card">
                        <span class="ws-public-icon ws-public-icon-sky" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 21V10m0 5c-4 0-7-2.5-7-6 4 0 7 2 7 6Zm0-3c0-4 2.5-7 6-7 0 4-2 7-6 7Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <div><h3>Ramah Lingkungan</h3><p>Bangun kebiasaan memilah dan menyetorkan sampah dengan lebih teratur.</p></div>
                    </article>
                </div>
            </div>
        </section>

        <section id="cara-kerja" class="ws-public-section ws-public-how">
            <div class="ws-public-container">
                <div class="ws-public-section-heading ws-public-section-heading-centered">
                    <span class="ws-public-eyebrow">Alur yang sederhana</span>
                    <h2>Cara Kerja Wrongshock</h2>
                    <p>Dari sampah yang sudah dipilah hingga nilai setoran masuk ke saldo anggota.</p>
                </div>
                <ol class="ws-public-step-grid">
                    <li class="ws-public-step-card ws-public-step-mint">
                        <span class="ws-public-step-number">01</span><span class="ws-public-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 7h12l-1 14H7L6 7Zm3 0V4h6v3M4 7h16" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
                        <h3>Pilah Sampah</h3><p>Pisahkan sampah berdasarkan jenisnya.</p>
                    </li>
                    <li class="ws-public-step-card ws-public-step-yellow">
                        <span class="ws-public-step-number">02</span><span class="ws-public-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 17h16M6 17V8l6-4 6 4v9M9 17v-5h6v5" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
                        <h3>Bawa dan Setorkan</h3><p>Bawa sampah ke pengelola bank sampah.</p>
                    </li>
                    <li class="ws-public-step-card ws-public-step-sky">
                        <span class="ws-public-step-number">03</span><span class="ws-public-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M5 20h14M7 20l2-9h6l2 9M9 11l3-7 3 7M8 15h8" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
                        <h3>Ditimbang dan Dicatat</h3><p>Petugas menimbang dan mencatat setoran.</p>
                    </li>
                    <li class="ws-public-step-card ws-public-step-green">
                        <span class="ws-public-step-number">04</span><span class="ws-public-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16v12H4zM4 10h16m-4 4h2" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
                        <h3>Saldo Bertambah</h3><p>Nilai setoran masuk ke saldo anggota setelah transaksi berhasil.</p>
                    </li>
                </ol>
            </div>
        </section>

        <section id="harga-sampah" class="ws-public-section ws-public-prices">
            <div class="ws-public-container">
                <div class="ws-public-section-heading ws-public-price-heading">
                    <div><span class="ws-public-eyebrow">Informasi master Wrongshock</span><h2>Harga Sampah Terbaru</h2><p>Cek harga berdasarkan data yang tersedia di Wrongshock.</p></div>
                    <span class="ws-public-price-note">Harga dapat berubah sesuai pembaruan dari pengelola.</span>
                </div>

                @if ($wasteItems->isEmpty())
                    <div class="ws-public-empty-state">
                        <span class="ws-public-icon ws-public-icon-mint" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16v12H4zM8 7V4h8v3M9 12h6" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
                        <h3>Informasi harga sampah belum tersedia.</h3>
                        <p>Silakan periksa kembali setelah pengelola memperbarui data harga.</p>
                    </div>
                @else
                    <div class="ws-public-price-grid">
                        @foreach ($wasteItems as $item)
                            <article class="ws-public-price-card">
                                <div class="ws-public-price-card-top">
                                    <span class="ws-public-price-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m12 3 3 5h-3c3 1 5 4 4 7M18 18h-6l2-3c-3 1-6-1-7-4M6 8l3-5 3 5H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                    <span class="ws-public-output">{{ $item->output }}</span>
                                </div>
                                <h3>{{ $item->category }}</h3>
                                <div class="ws-public-price-value"><strong>Rp{{ number_format($item->price, 0, ',', '.') }}</strong><span>per {{ $item->unit }}</span></div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section id="informasi" class="ws-public-section ws-public-education">
            <div class="ws-public-container ws-public-education-grid">
                <div class="ws-public-education-visual">
                    <span class="ws-public-education-badge">Siap disetorkan</span>
                    <img src="{{ asset('images/wrongshock/eco-community.svg') }}" alt="" aria-hidden="true">
                    <span class="ws-public-education-ring" aria-hidden="true"></span>
                </div>
                <div>
                    <div class="ws-public-section-heading">
                        <span class="ws-public-eyebrow">Informasi sederhana</span>
                        <h2>Sampah Bersih,<br>Nilainya Lebih Baik</h2>
                        <p>Persiapkan sampah sebelum dibawa agar lebih mudah dipilah dan dicatat oleh pengelola.</p>
                    </div>
                    <ul class="ws-public-tip-list">
                        <li><span aria-hidden="true">✓</span>Pisahkan sampah berdasarkan jenis.</li>
                        <li><span aria-hidden="true">✓</span>Kosongkan isi botol atau wadah sebelum disetor.</li>
                        <li><span aria-hidden="true">✓</span>Jaga sampah kertas tetap kering.</li>
                        <li><span aria-hidden="true">✓</span>Pisahkan material yang berbeda jika memungkinkan.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section id="tentang" class="ws-public-section ws-public-why">
            <div class="ws-public-container">
                <div class="ws-public-section-heading ws-public-section-heading-centered">
                    <span class="ws-public-eyebrow">Pencatatan yang lebih jelas</span><h2>Kenapa Wrongshock?</h2>
                    <p>Wrongshock membantu pengelolaan bank sampah dan informasi anggota menjadi lebih terorganisir.</p>
                </div>
                <div class="ws-public-reason-grid">
                    <article><span>01</span><h3>Transaksi Tercatat</h3><p>Setoran tersimpan sebagai riwayat anggota.</p></article>
                    <article><span>02</span><h3>Saldo Mudah Dipantau</h3><p>Anggota dapat melihat saldo melalui akun mereka.</p></article>
                    <article><span>03</span><h3>Harga Lebih Transparan</h3><p>Informasi jenis dan harga sampah tersedia dengan jelas.</p></article>
                    <article><span>04</span><h3>Riwayat Terorganisir</h3><p>Setoran dapat dilihat kembali secara digital.</p></article>
                </div>
            </div>
        </section>

        <section class="ws-public-cta-section">
            <div class="ws-public-container">
                <div class="ws-public-cta-card">
                    <div>
                        <span class="ws-public-eyebrow">Mulai dari sekarang</span>
                        <h2>Langkah Kecil untuk<br>Lingkungan yang Lebih Baik</h2>
                        <p>Gunakan Wrongshock untuk melihat informasi sampah dan mencatat perjalanan setoran secara lebih terorganisir.</p>
                        <div class="ws-public-cta-actions">
                            @if ($memberDashboardUrl)
                                <a href="{{ $memberDashboardUrl }}" class="ws-public-button ws-public-button-light">Buka Beranda</a>
                            @elseif (! auth()->check())
                                @if ($registrationUrl)<a href="{{ $registrationUrl }}" class="ws-public-button ws-public-button-light">Daftar Sekarang</a>@endif
                                <a href="{{ $loginUrl }}" class="ws-public-button ws-public-button-outline-light">Masuk</a>
                            @else
                                <a href="#harga-sampah" class="ws-public-button ws-public-button-light">Lihat Harga Sampah</a>
                            @endif
                        </div>
                    </div>
                    <div class="ws-public-cta-visual" aria-hidden="true">
                        <svg viewBox="0 0 220 180" fill="none"><circle cx="110" cy="88" r="58" fill="#62B9D4"/><path d="M70 61c14-18 35-26 56-21l9 13-7 14-18 3-8 14-18-3-16-10 2-10Zm4 45 18-11 19 7 8 20-12 13-3 17-18-8-12-21v-17Zm62-17 17 3 11 17-8 22-17 15-11-6 2-18-10-12 16-21Z" fill="#48A56D"/><path d="M67 90c-19-7-29-18-32-34m118 34c19-7 29-18 32-34" stroke="#DDF4E7" stroke-width="8" stroke-linecap="round"/><path d="M105 109c8 7 16 7 24 0" stroke="#134E3A" stroke-width="4" stroke-linecap="round"/><circle cx="98" cy="91" r="4" fill="#134E3A"/><circle cx="132" cy="91" r="4" fill="#134E3A"/><path d="M29 153c31-24 60-13 84-1 30 15 54-5 78-10 12-3 21 0 29 5v33H0v-9c8-3 18-9 29-18Z" fill="#8FD1AA"/></svg>
                    </div>
                    <span class="ws-public-cta-leaf ws-public-cta-leaf-one" aria-hidden="true"></span><span class="ws-public-cta-leaf ws-public-cta-leaf-two" aria-hidden="true"></span>
                </div>
            </div>
        </section>
    </main>

    <footer class="ws-public-footer">
        <div class="ws-public-container ws-public-footer-grid">
            <div>
                <a href="#beranda" class="ws-public-brand ws-public-brand-footer">
                    <span class="ws-public-brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M16 27C9.4 27 5 22.4 5 16.3 5 9.2 10.7 5 17.2 5c4.2 0 7.7 2.1 9.8 5.4-3.1-.7-6.5.1-8.9 2.3-2.8 2.6-3.6 6.5-2.5 9.9" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><path d="M15.7 22.6c1.7-5 5-8.8 10.2-11.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></span>
                    <span><strong>Wrongshock</strong><small>Bank Sampah Digital</small></span>
                </a>
                <p>Platform digital untuk membantu pengelolaan bank sampah dan pencatatan setoran anggota secara lebih terorganisir.</p>
            </div>
            <div class="ws-public-footer-links"><h2>Navigasi</h2><a href="#beranda">Beranda</a><a href="#cara-kerja">Cara Kerja</a><a href="#harga-sampah">Harga Sampah</a><a href="#informasi">Informasi</a></div>
            <div class="ws-public-footer-links">
                <h2>Akun Anggota</h2>
                @if ($memberDashboardUrl)
                    <a href="{{ $memberDashboardUrl }}">Buka Beranda</a>
                @elseif (! auth()->check())
                    <a href="{{ $loginUrl }}">Masuk</a>
                    @if ($registrationUrl)<a href="{{ $registrationUrl }}">Daftar Sekarang</a>@endif
                @else
                    <a href="#harga-sampah">Lihat Harga Sampah</a>
                @endif
            </div>
        </div>
        <div class="ws-public-container ws-public-footer-bottom"><span>&copy; {{ now()->year }} Wrongshock</span><span>Bersama untuk lingkungan lebih baik.</span></div>
    </footer>

    <script>
        document.querySelectorAll('.ws-public-mobile-menu a').forEach((link) => {
            link.addEventListener('click', () => link.closest('details').removeAttribute('open'))
        })
    </script>
</body>
</html>
