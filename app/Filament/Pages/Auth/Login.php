<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected static string $view = 'filament.auth.login';

    protected ?string $maxWidth = 'full';

    protected array $extraBodyAttributes = ['class' => 'ws-auth-page'];

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

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Email')
            ->placeholder('nama@email.com')
            ->autocomplete('email');
    }

    protected function getPasswordFormComponent(): Component
    {
        $component = parent::getPasswordFormComponent()->label('Password');
        $actions = $component->getSuffixActions();

        $actions['showPassword']?->label('Tampilkan password');
        $actions['hidePassword']?->label('Sembunyikan password');

        return $component;
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()->label('Ingat saya');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Masuk ke Dashboard');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Masuk sebagai Admin | Wrongshock';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Masuk sebagai Admin';
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Email atau password tidak sesuai.',
        ]);
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        return Notification::make()
            ->danger()
            ->title('Terlalu banyak percobaan masuk.')
            ->body("Silakan coba lagi dalam {$exception->secondsUntilAvailable} detik.");
    }
}
