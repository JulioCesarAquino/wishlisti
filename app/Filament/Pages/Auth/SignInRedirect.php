<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;

/**
 * The panel's login page sends people to /entrar, which has the look of
 * the home page. Kept as the panel's login so every "sign in first"
 * redirect, and the password reset's way back, still land there.
 */
class SignInRedirect extends Login
{
    public function mount(): void
    {
        $this->redirect(route('sign-in'));
    }
}
