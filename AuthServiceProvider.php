<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('AccessToAdminpanel', function ($user) {
            return (in_array($user->role_id, [3,4,5]) AND $user->status == 1);
        });

        Gate::define('AccessToReports', function ($user) {
            return (in_array($user->role_id, [2,5]) AND $user->status == 1);
        });

    }
}
