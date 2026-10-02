<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLogViewer();
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
