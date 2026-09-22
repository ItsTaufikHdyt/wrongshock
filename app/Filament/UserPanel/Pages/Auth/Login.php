<?php

namespace App\Filament\UserPanel\Pages\Auth;

use App\Filament\Pages\Auth\Login as BaseLogin;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Masuk ke Akun');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Masuk';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Selamat Datang!';
    }
}
