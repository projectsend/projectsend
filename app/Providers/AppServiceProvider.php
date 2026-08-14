<?php

namespace App\Providers;

use App\Modules\Identity\Passwords\PasswordPolicy;
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
        // Every password field in the app validates with Password::defaults()
        // — setup, client registration, admin-set passwords, self-service
        // change, reset. Laravel's bare default is min(8) and nothing else;
        // what this installation asks for instead lives in PasswordPolicy.
        //
        // The closure matters: Password::defaults() invokes it at validation
        // time, not here, so the policy can read settings out of the database
        // without this provider needing one at boot.
        Password::defaults(fn (): Password => $this->app->make(PasswordPolicy::class)->rule());
    }
}
