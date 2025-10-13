<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>WRONGSHOCK INDONESIA - Registrasi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            background: linear-gradient(135deg,  #b5c267, #20c997);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Poppins", sans-serif;
        }

        .card {
            border-radius: 20px;
            overflow: hidden;
        }

        .card-header {
            background: #fff;
            border-bottom: none;
            text-align: center;
            padding: 2rem 1rem 1rem;
        }

        .card-header i {
            font-size: 3rem;
            color: #28a745;
        }

        .form-control {
            border-radius: 10px;
        }

        .btn-success {
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .text-link {
            color: #20c997;
            font-weight: 500;
            text-decoration: none;
        }

        .text-link:hover {
            text-decoration: underline;
        }

        input.is-valid {
            border-color: #28a745 !important;
        }

        input.is-invalid {
            border-color: #dc3545 !important;
        }
    </style>
</head>

<body>
    <div class="container">
        @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3 border-0 mt-3 px-4 py-3 d-flex align-items-center"
            role="alert" style="background: linear-gradient(135deg, #b5c267, #ffe27c); color: #fff;">
            <i class="fa-solid fa-circle-check me-3 fs-4"></i>
            <div class="flex-grow-1">
                <strong>Berhasil!</strong> {{ session('success') }}
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-header">
                        <i class="fa-solid fa-recycle"></i>
                        <h3 class="mt-3 fw-bold">Registrasi Akun</h3>
                        <p class="text-muted mb-0">Bergabung dengan platform pengelolaan sampah digital</p>
                    </div>
                    <div class="card-body px-4 py-4">
                        <form action="{{ route('user.register') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="name" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="name" name="name"
                                    placeholder="Masukkan nama lengkap" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Alamat Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    placeholder="Masukkan email" required>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Jalan</label>
                                <input type="text" class="form-control" id="address" name="address"
                                    placeholder="Masukkan Alamat" required>
                            </div>
                            <div class="mb-3">
                                <label for="district" class="form-label">Kecamatan</label>
                                <select class="form-control" name="district" id="district" required>
                                    <option value="">-- Pilih Kecamatan --</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="sub_district" class="form-label">Kelurahan</label>
                                <select class="form-control" name="sub_district" id="sub_district" required>
                                    <option value="">-- Pilih Kelurahan --</option>
                                </select>
                            </div>
                            <div class="mb-3 position-relative">
                                <label for="password" class="form-label">Kata Sandi</label>
                                <input type="password" class="form-control" id="password" name="password"
                                    placeholder="Masukkan kata sandi" required>
                                <i class="fa-solid fa-eye position-absolute top-50 end-0 translate-middle-y me-3"
                                    id="togglePassword" style="cursor: pointer;"></i>
                            </div>

                            <div class="mb-3 position-relative">
                                <label for="password_confirmation" class="form-label">Konfirmasi Kata Sandi</label>
                                <input type="password" class="form-control" id="password_confirmation"
                                    name="password_confirmation" placeholder="Ulangi kata sandi" required>
                                <i class="fa-solid fa-eye position-absolute top-50 end-0 translate-middle-y me-3"
                                    id="toggleConfirmationPassword" style="cursor: pointer;"></i>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="fa-solid fa-user-plus me-2"></i> Daftar
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <small>Sudah punya akun? <a href="{{ url('/') }}" class="text-link">Login di
                                    sini</a></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function () {
        // Load daftar kecamatan
        $.get("{{ url('/api/districts') }}", function (data) {
            $.each(data, function (id, name) {
                $('#district').append(new Option(name, id));
            });
        });

        // Saat kecamatan dipilih, load kelurahan sesuai kecamatan
        $('#district').on('change', function () {
            let districtId = $(this).val();
            $('#sub_district').empty().append(new Option("-- Pilih Kelurahan --", ""));

            if (districtId) {
                $.get("{{ url('/api/subdistricts') }}?district_id=" + districtId, function (data) {
                    $.each(data, function (id, name) {
                        $('#sub_district').append(new Option(name, id));
                        });
                    });
                }
            });
        });
        // Toggle password visibility
        const togglePassword = document.querySelector("#togglePassword");
        const password = document.querySelector("#password");
        togglePassword.addEventListener("click", function () {
            const type = password.getAttribute("type") === "password" ? "text" : "password";
            password.setAttribute("type", type);
            this.classList.toggle("fa-eye-slash");
        });
        const toggleConfirmationPassword = document.querySelector("#toggleConfirmationPassword");
        const password2 = document.querySelector("#password_confirmation");
        toggleConfirmationPassword.addEventListener("click", function () {
            const type = password2.getAttribute("type") === "password" ? "text" : "password";
            password2.setAttribute("type", type);
            this.classList.toggle("fa-eye-slash");
        });

            document.addEventListener("DOMContentLoaded", function () {
                const password = document.getElementById("password");
                const confirmPassword = document.getElementById("password_confirmation");

                confirmPassword.addEventListener("input", function () {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.classList.add("is-invalid");
                    confirmPassword.classList.remove("is-valid");
                } else {
                    confirmPassword.classList.add("is-valid");
                    confirmPassword.classList.remove("is-invalid");
                }
            });
            });

    </script>
</body>

</html>