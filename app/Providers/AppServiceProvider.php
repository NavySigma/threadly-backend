<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // Strict mode — prevent mass assignment vulnerability
        Model::shouldBeStrict(! app()->isProduction());

        // Morph map — map string ke full class untuk polymorphic relations
        Relation::morphMap([
            'post' => Post::class,
            'comment' => Comment::class,
            'user' => User::class,
        ]);

        // Log slow queries (> 1 detik)
        DB::whenQueryingForLongerThan(1000, function () {
            logger()->warning('Slow query detected');
        });

        // Saat local/testing, naikkan limit agar Cypress test tidak kena throttle
        $isLocal = app()->isLocal() || app()->environment('testing');

        // User login — maksimal 5x/menit per IP (prod), 500x/menit (local)
        RateLimiter::for('auth', function (Request $request) use ($isLocal) {
            return Limit::perMinute($isLocal ? 500 : 5)->by($request->ip());
        });

        // Forgot password — maksimal 3x/menit per IP (prod), 500x/menit (local)
        RateLimiter::for('forgot-password', function (Request $request) use ($isLocal) {
            return Limit::perMinute($isLocal ? 500 : 3)->by($request->ip());
        });

        // Vote & like — maksimal 30x/menit per user (prod), 500x/menit (local)
        RateLimiter::for('interaction', function (Request $request) use ($isLocal) {
            return Limit::perMinute($isLocal ? 500 : 30)->by($request->user()?->id ?? $request->ip());
        });

        // Buat post & comment — maksimal 10x/menit per user (prod), 500x/menit (local)
        RateLimiter::for('write', function (Request $request) use ($isLocal) {
            return Limit::perMinute($isLocal ? 500 : 10)->by($request->user()?->id ?? $request->ip());
        });

        // Search — maksimal 30x/menit per IP (prod), 500x/menit (local)
        RateLimiter::for('search', function (Request $request) use ($isLocal) {
            return Limit::perMinute($isLocal ? 500 : 30)->by($request->ip());
        });

        // API umum — 60x/menit user login, 20x/menit guest (prod), 500x/menit (local)
        RateLimiter::for('api', function (Request $request) use ($isLocal) {
            if ($isLocal) {
                return Limit::perMinute(500)->by($request->user()?->id ?? $request->ip());
            }
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)
                : Limit::perMinute(20)->by($request->ip());
        });
    }
}
