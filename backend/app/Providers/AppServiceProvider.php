<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Transfer;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\AssetCategoryPolicy;
use App\Policies\AssetPolicy;
use App\Policies\LocationPolicy;
use App\Policies\TransferPolicy;
use App\Policies\UserPolicy;
use App\Services\DashboardService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->registerPolicies();
        $this->registerRateLimiters();
        $this->registerCacheInvalidation();
        $this->registerDocsGate();
    }

    private function registerPolicies(): void
    {
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(AssetCategory::class, AssetCategoryPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Transfer::class, TransferPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                strtolower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(120)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        RateLimiter::for('reports', function (Request $request): Limit {
            return Limit::perMinute(30)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }

    /**
     * Akses dokumentasi API di luar lingkungan lokal.
     *
     * Di `local` dokumentasi terbuka untuk semua orang agar mudah dicoba.
     * Pada lingkungan lain, hanya admin yang boleh membuka dokumentasi
     * karena isinya memuat seluruh endpoint beserta aturan bisnisnya.
     */
    private function registerDocsGate(): void
    {
        Gate::define('viewApiDocs', function (?User $user): bool {
            return $user !== null
                && $user->is_active
                && $user->hasRole(UserRole::Admin);
        });
    }

    /**
     * Ringkasan dashboard diinvalidasi setiap kali data aset atau transfer
     * berubah, agar angka yang tampil tidak basi.
     */
    private function registerCacheInvalidation(): void
    {
        $invalidate = static function (): void {
            DashboardService::invalidate();
        };

        Event::listen([
            'eloquent.saved: '.Asset::class,
            'eloquent.deleted: '.Asset::class,
            'eloquent.saved: '.Transfer::class,
            'eloquent.deleted: '.Transfer::class,
        ], $invalidate);
    }
}
