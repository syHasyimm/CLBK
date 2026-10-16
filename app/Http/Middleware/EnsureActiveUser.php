<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // Baca ulang agar sesi lama mengikuti penonaktifan dan perubahan role.
        $user = $request->user()?->fresh();
        if (! $user || ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                abort(403, 'Akun Anda sudah dinonaktifkan.');
            }

            return redirect()->route('login')->withErrors(['email' => 'Akun Anda sudah dinonaktifkan. Hubungi manajer.']);
        }
        Auth::guard('web')->setUser($user);

        return $next($request);
    }
}
