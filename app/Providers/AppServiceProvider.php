<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\OptimisationTrajetService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Enregistrer le service d'optimisation des trajets comme singleton
        $this->app->singleton(OptimisationTrajetService::class, function ($app) {
            return new OptimisationTrajetService();
        });

        // Pour faciliter l'injection de dépendances
        $this->app->alias(OptimisationTrajetService::class, 'optimisation.trajet');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
