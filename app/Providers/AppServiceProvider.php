<?php

namespace App\Providers;

use App\Enums\FinanceKind;
use App\Enums\MasterResource;
use App\Enums\UserRole;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\FactorySale;
use App\Models\SortSale;
use App\Models\Trip;
use App\Models\User;
use App\Models\Weighing;
use App\Policies\CashAccountPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\FinancePolicy;
use App\Policies\ManagerPolicy;
use App\Policies\SalePolicy;
use App\Policies\TripPolicy;
use App\Policies\WeighingPolicy;
use App\Support\Database\MySqlQueryGrammar;
use App\Support\ProductionSecurity;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app['events']->listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            if ($event->connection->getDriverName() === 'mysql') {
                $event->connection->setQueryGrammar(new MySqlQueryGrammar($event->connection));
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app(ProductionSecurity::class)->validate();

        Password::defaults(fn () => Password::min(12)->letters()->numbers());

        // Recovery links must never inherit an untrusted request Host header.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => rtrim((string) config('app.url'), '/').route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ], false)
        );

        RateLimiter::for('auth-public', fn (Request $request) => Limit::perMinute(20)->by('auth-public:'.$request->ip())
        );
        RateLimiter::for('password-recheck', fn (Request $request) => Limit::perMinute(5)->by('password-recheck:'.$request->user()->getAuthIdentifier())
        );
        RateLimiter::for('proof-uploads', fn (Request $request) => Limit::perMinute(10)->by('proof-uploads:'.$request->user()->getAuthIdentifier())
        );
        RateLimiter::for('whatsapp-connection', fn (Request $request) => Limit::perMinute(5)->by('whatsapp-connection:'.$request->user()->getAuthIdentifier()));

        Gate::policy(User::class, ManagerPolicy::class);
        Gate::policy(Trip::class, TripPolicy::class);
        Gate::policy(Weighing::class, WeighingPolicy::class);
        Gate::policy(FactorySale::class, SalePolicy::class);
        Gate::policy(SortSale::class, SalePolicy::class);
        foreach (FinanceKind::cases() as $kind) {
            Gate::policy($kind->model(), FinancePolicy::class);
        }
        Gate::define('manage-finance-capital', fn (User $user) => (new ManagerPolicy)->viewAny($user));
        foreach (MasterResource::cases() as $resource) {
            Gate::policy($resource->model(), ManagerPolicy::class);
        }
        Gate::policy(CashAccount::class, CashAccountPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::define('manage-settings', fn (User $user) => (new ManagerPolicy)->viewAny($user));
        Gate::define('view-whatsapp', fn (User $user) => $user->is_active && in_array($user->role, [UserRole::Manager, UserRole::Cashier], true));
        Gate::define('manage-prices', fn (User $user) => $user->is_active && in_array($user->role, [UserRole::Manager, UserRole::Cashier], true));
        Gate::define('view-prices', fn (User $user) => $user->is_active && in_array($user->role, [UserRole::Manager, UserRole::Cashier], true));
        Vite::prefetch(concurrency: 3);
    }
}
