<?php

namespace App\Providers;

use App\Models\Events\Event;
use App\Models\User;
use App\Notifications\Identity\PasswordResetNotification;
use Carbon\CarbonImmutable;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The panel's "forgot my password" e-mail: ours, in the Wishlisti
        // look and sent right away (Filament's is queued, and there's no
        // queue worker — it would never go out).
        $this->app->bind(FilamentResetPassword::class, fn ($app, array $parameters) => new PasswordResetNotification($parameters['token']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLogViewer();

        // Dates are stored in UTC; the panel shows them in Brasília time.
        FilamentTimezone::set(Event::TIMEZONE);
    }

    /**
     * The log screen (/admin/logs) is for the admin only, in every
     * environment. Files can be read and downloaded, not deleted: they're
     * removed on their own after LOG_DAILY_DAYS.
     */
    protected function configureLogViewer(): void
    {
        Gate::define('viewLogViewer', fn (?User $user): bool => (bool) $user?->isAdmin());
        Gate::define('deleteLogFile', fn (?User $user): bool => false);
        Gate::define('deleteLogFolder', fn (?User $user): bool => false);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // At least 8 characters, with an upper and a lower case letter, a
        // number and a symbol — everywhere, so the tests see it too. Leaked
        // passwords (haveibeenpwned) are refused only in production, where
        // asking that service is fine.
        Password::defaults(function (): Password {
            $rule = Password::min(8)->mixedCase()->numbers()->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
