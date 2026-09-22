<?php

namespace App\Filament\UserPanel\Pages\Auth;

use App\Filament\Pages\Auth\Login as BaseLogin;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->label('Email');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->label('Password');
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()->label('Ingat saya');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Masuk');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Masuk';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Masuk ke akun Wrongshock';
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
