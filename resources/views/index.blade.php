<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>WRONGSHOCK INDONESIA - Platform Pengelolaan Sampah</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="{{ url('css/style.css') }}">
</head>

<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <div class="container">
      <a class="navbar-brand" href="#">WRONGSHOCK INDONESIA</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="#tentang">Tentang Kami</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#layanan">Layanan</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#mitra">Mitra</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#kontak">Kontak</a>
          </li>
          <li class="nav-item dropdown">
            <a class="btn btn-login dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
              aria-expanded="false">
              Masuk
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="{{ url('admin') }}">Masuk sebagai Admin</a></li>
              <li><a class="dropdown-item" href="{{ url('user') }}">Masuk sebagai User</a></li>
            </ul>
          </li>

        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero-section" style="margin-top: 80px">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-12">
          <h1 class="hero-title">
            Selamat Datang,<br />
            di <span class="brand">WRONGSHOCK!</span>
          </h1>
          <p class="lead">
            Sebuah platform pengelolaan sampah yang lahir dari kegelisahan akan buruknya
            <br>
            sistem dan kesadaran pengelolaan limbah di masyarakat Kota Bontang
          </p>
          <a class="btn btn-register" href="{{ url('register') }}">Daftar</a>
        </div>
        <div class="col-lg-12">
          <div class="banner-image"
            style="background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('{{ asset('assets/image/bg1.png') }}');">
            <div class="banner-text">#RongsokJadiPasok</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Purpose Section -->
  <section class="py-5" id="tentang">
    <div class="container">
      <h2 class="section-title ">Apa Tujuan Kami?</h2>
      <div class="row">
        <div class="col-lg-4 mb-4">
          <div class="card purpose-card">
            <img src="{{ asset('assets/image/img1.png') }}" class="purpose-icon">
            <h4>Sadar</h4>
            <p>
              Membuat masyarakat lebih sadar terkait dampak negatif sampah terhadap lingkungan.
            </p>
          </div>
        </div>
        <div class="col-lg-4 mb-4">
          <div class="card purpose-card">
            <img src="{{asset('assets/image/img2.png')}}" class="purpose-icon">
            <h4>Sistem</h4>
            <p>
              Menciptakan sistem yang memudahkan semua pihak untuk saling terhubung dalam ekosistem pengelolaan sampah.
            </p>
          </div>
        </div>
        <div class="col-lg-4 mb-4">
          <div class="card purpose-card">
            <img src="{{asset('assets/image/img3.png')}}" class="purpose-icon">
            <h4>Gaya Hidup</h4>
            <p>
              Menjadikan pengelolaan sampah sebagai bagian dari gaya hidup sehari-hari, bukan hanya kampanye sesaat.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Why Section -->
  <section class="why-section" id="layanan">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <h2 class="section-title">
            Mengapa harus bergabung dengan WRONGSHOCK?
          </h2>

          <div class="mb-4">
            <h5><strong>Perubahan yang Bermakna</strong></h5>
            <p>
              Menantang pola lama, menciptakan gagasan baru, dan menggerakkan transformasi sosial, budaya, maupun
              industri kreatif.
            </p>
          </div>

          <div class="mb-4">
            <h5><strong>Kolaborasi Tanpa Batas</strong></h5>
            <p>
              Tidak ada hirarki yang mengekang kreativitas, melainkan semangat kolektif menciptakan sesuatu yang lebih
              besar dari diri sendiri.
            </p>
          </div>

          <div class="mb-4">
            <h5><strong>Kesejahteraan sebagai Pondasi</strong></h5>
            <p>
              Keseimbangan antara kerja dan hidup, kesehatan mental, serta rasa aman untuk berkembang.
            </p>
          </div>

          <div class="mt-4">
            <div class="dropdown d-inline">
                <button class="btn btn-contact dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Masuk
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ url('admin') }}">Masuk sebagai Admin</a></li>
                    <li><a class="dropdown-item" href="{{ url('user') }}">Masuk sebagai User</a></li>
                </ul>
            </div>
            <a class="btn btn-outline-contact" href="https://wa.me/6281234567890" target="_blank" rel="noopener">
                Hubungi Kami
            </a>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="why-image" style="background-image: url('{{ asset('assets/image/why.png') }}');"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- SDG Section -->
  <section class="py-5" id="mitra">
    <div class="container">
      <h2 class="section-title">Prinsip Perjalanan WRONGSHOCK</h2>
      <div class="row">
        <div class="col-lg-4 mb-4">
          <div class="sdg-card">
            <div class="sdg-header">
              <img src="{{asset('assets/image/sustainable.png')}}" class="img-fluid" alt="">
            </div>
            <div class="sdg-content">
              <h5>Kota dan Pemukiman yang Berkelanjutan</h5>
              <p>
                Meningkatkan kualitas hidup penduduk kota dengan menyediakan
                akses yang lebih baik, termasuk yang terkait dengan
                pengelolaan limbah yang ramah lingkungan
              </p>
            </div>
          </div>
        </div>
        <div class="col-lg-4 mb-4">
          <div class="sdg-card">
            <div class="sdg-header">
              <img src="{{asset('assets/image/responsible.png')}}" class="img-fluid" alt="">
            </div>
            <div class="sdg-content">
              <h5>Konsumsi dan Produksi yang Bertanggung Jawab</h5>
              <p>
                Mendorong pola konsumsi dan produksi yang berkelanjutan untuk
                mengurangi limbah serta mendorong praktik bisnis yang ramah
                lingkungan
              </p>
            </div>
          </div>
        </div>
        <div class="col-lg-4 mb-4">
          <div class="sdg-card">
            <div class="sdg-header">
              <img src="{{asset('assets/image/life.png')}}" class="img-fluid" alt="">
            </div>
            <div class="sdg-content">
              <h5>Ekosistem Lautan</h5>
              <p>
                Melestarikan dan memanfaatkan secara berkelanjutan lautan,
                laut, dan sumber daya laut demi pembangunan yang berkelanjutan
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Information and Education Section -->
  <section class="py-5" style="background: var(--light-gray)">
    <div class="container">
      <h2 class="section-title">Informasi dan Edukasi</h2>
      <div class="row g-4 mb-4">
        <div class="col-md-4">
          <div class="info-card">
            <h5>"Pengelolaan Sampah Terkini melalui Reduce, Reuse, Recycle (3R) di Bontang"</h5>
            <div class="author-info">
              <img src="{{asset('assets/image/woman.png')}}" alt="Writer" class="author-avatar">
              <div class="author-details">
                <h6>Ibu Siti</h6>
                <p>IRT</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="info-card">
            <h5>"Peran Pemerintah Kota Bontang dalam Pengelolaan Sampah di TPA"</h5>
            <div class="author-info">
              <img src="{{asset('assets/image/man.png')}}" alt="Writer" class="author-avatar">
              <div class="author-details">
                <h6>Pak Handoko</h6>
                <p>Karyawan Swasta</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="info-card">
            <h5>"Kisah Ibu Rahmawati dalam Mengelola Persampahan di Guntung"</h5>
            <div class="author-info">
              <img src="{{asset('assets/image/woman.png')}}" alt="Writer" class="author-avatar">
              <div class="author-details">
                <h6>Ibu Rahmawati</h6>
                <p>IRT</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer" id="kontak">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <div class="footer-brand">WRONGSHOCK INDONESIA</div>
          <div class="social-icons">
            <a href="#"><i class="fab fa-facebook"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-youtube"></i></a>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="footer-buttons">
            <div class="dropdown">
              <button class="btn btn-login dropdown-toggle" type="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                Masuk
              </button>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="{{ url('admin') }}">Masuk sebagai Admin</a></li>
                <li><a class="dropdown-item" href="{{ url('user') }}">Masuk sebagai User</a></li>
              </ul>
            </div>

          </div>
        </div>
      </div>
    </div>
  </footer>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    // Smooth scrolling for navigation links
      document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener("click", function (e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute("href"));
          if (target) {
            target.scrollIntoView({
              behavior: "smooth",
              block: "start",
            });
          }
        });
      });

      // Add scroll effect to navbar
      window.addEventListener("scroll", function () {
        const navbar = document.querySelector(".navbar");
        if (window.scrollY > 50) {
          navbar.style.background = "rgba(255, 255, 255, 0.95)";
          navbar.style.backdropFilter = "blur(10px)";
        } else {
          navbar.style.background = "white";
          navbar.style.backdropFilter = "none";
        }
      });

      // Add animation to cards on scroll
      const observerOptions = {
        threshold: 0.1,
        rootMargin: "0px 0px -50px 0px",
      };

      const observer = new IntersectionObserver(function (entries) {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.style.opacity = "1";
            entry.target.style.transform = "translateY(0)";
          }
        });
      }, observerOptions);

      // Apply animation to cards
      document.querySelectorAll(".card").forEach((card) => {
        card.style.opacity = "0";
        card.style.transform = "translateY(20px)";
        card.style.transition = "opacity 0.6s ease, transform 0.6s ease";
        observer.observe(card);
      });
  </script>
</body>

</html>