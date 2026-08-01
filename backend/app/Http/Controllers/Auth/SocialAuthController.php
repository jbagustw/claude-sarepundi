<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles "Login with Google/Facebook/Apple" for the SPA. This controller
 * lives on the `web` route group (not `api`), because the OAuth callback
 * is a top-level browser navigation coming from the provider's domain, so
 * Sanctum's EnsureFrontendRequestsAreStateful (which gates on the Referer
 * header matching a known frontend domain) would not reliably start a
 * session for it. The `web` session cookie is the same one the SPA's
 * `/api/*` requests already rely on, so logging in here is enough for the
 * frontend to be authenticated once redirected back.
 */
class SocialAuthController extends Controller
{
    /** @var string[] */
    private const SUPPORTED_PROVIDERS = ['google'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->assertSupported($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $this->assertSupported($provider);

        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        try {
            /** @var SocialiteUser $socialUser */
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            return redirect("{$frontendUrl}/login?social_error=1");
        }

        if (! $socialUser->getEmail()) {
            return redirect("{$frontendUrl}/login?social_error=no_email");
        }

        $user = User::where('provider', $provider)->where('provider_id', $socialUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                $user->forceFill(['provider' => $provider, 'provider_id' => $socialUser->getId()])->save();
            } else {
                $user = User::create([
                    'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: Str::before($socialUser->getEmail(), '@'),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                ]);
                // Social login only ever creates pencari (user) accounts — mitra
                // registration requires business details the OAuth profile can't provide.
                $user->assignRole('user');
            }
        }

        if ($user->status === 'suspended') {
            return redirect("{$frontendUrl}/login?social_error=suspended");
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Mirrors the frontend's ROLE_HOME map. New accounts are always
        // 'user' (see above), but an existing mitra/admin account could
        // also be the one linking their Google login, so resolve by role
        // rather than assuming 'user'.
        $roleHome = match ($user->getRoleNames()->first()) {
            'mitra' => '/mitra/dashboard',
            'admin' => '/admin/dashboard',
            default => '/user/dashboard',
        };

        return redirect("{$frontendUrl}{$roleHome}");
    }

    private function assertSupported(string $provider): void
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw new NotFoundHttpException();
        }
    }
}
