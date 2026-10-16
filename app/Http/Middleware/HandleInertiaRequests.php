<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Services\BusinessCash;
use App\Services\CashLedger;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email', 'email_verified_at', 'role', 'is_active']),
            ],
            'business' => fn () => ['name' => AppSetting::values()['business_name']],
            'cash' => function () use ($request) {
                $user = $request->user();
                if (! $user?->is_active || ! in_array($user->role, [UserRole::Manager, UserRole::Cashier], true)) {
                    return null;
                }
                $account = app(BusinessCash::class)->account();

                return $account ? $account->only(['id', 'name', 'is_active'])
                    + ['balance_idr' => app(CashLedger::class)->balance($account->id)] : null;
            },
            'flash' => ['success' => fn () => $request->session()->get('success')],
        ];
    }
}
