<?php

namespace App\Filament\Pages\Auth;

use Filament\Facades\Filament;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Models\Contracts\FilamentUser;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Validation\ValidationException;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $e) {
            $this->getRateLimitedNotification($e)?->send();
            return null;
        }

        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        // Pastikan user boleh akses panel (standar Filament)
        if (($user instanceof FilamentUser) && ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            Filament::auth()->logout();
            $this->throwFailureValidationException();
        }

        // Tambahan: blokir jika status != 1
        if ((int) ($user->status ?? 0) !== 1) {
            Filament::auth()->logout();

            throw ValidationException::withMessages([
                'data.email' => 'Akun Anda tidak aktif.',
            ]);
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
