<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\MitraProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole($data['role']);

            if ($data['role'] === 'mitra') {
                MitraProfile::create([
                    'user_id' => $user->id,
                    'business_name' => $data['business_name'],
                    'business_address' => $data['business_address'] ?? null,
                    'status' => 'pending',
                ]);
            }

            return $user;
        });

        $user->refresh();

        event(new Registered($user));

        $token = $this->establishSession($request, $user, $data['device_name'] ?? null);

        return (new UserResource($user->load('mitraProfile')))
            ->additional(array_filter(['token' => $token]))
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // Only email/password go to the guard — the optional device_name
        // field is ours (token naming), not a user column, and both
        // Auth::attempt()/validate() treat every array key as a where()
        // clause.
        $credentials = $request->only(['email', 'password']);

        if ($request->hasSession()) {
            // Web SPA: Auth::attempt() both verifies the credentials and
            // establishes the session in one call.
            if (! Auth::attempt($credentials, remember: true)) {
                throw ValidationException::withMessages([
                    'email' => ['Email atau password salah.'],
                ]);
            }
            $user = Auth::user();
        } else {
            // Mobile: verify credentials WITHOUT touching the 'web' guard.
            // Auth::attempt() would log the user into the 'web' SessionGuard
            // as a side effect even though nothing here ever sends that
            // guard's cookie back — and Sanctum's own guard resolution
            // (Laravel\Sanctum\Guard::__invoke()) checks the 'web' guard
            // BEFORE it ever looks at the bearer token, so an incidentally-
            // authenticated 'web' guard would silently shadow the token
            // this call is about to issue. Auth::validate() checks the
            // credentials the same way but never logs anyone in anywhere.
            if (! Auth::validate($credentials)) {
                throw ValidationException::withMessages([
                    'email' => ['Email atau password salah.'],
                ]);
            }
            $user = User::where('email', $credentials['email'])->first();
        }

        if ($user->status === 'suspended') {
            if ($request->hasSession()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            throw ValidationException::withMessages([
                'email' => ['Akun anda telah disuspend. Hubungi admin.'],
            ]);
        }

        $token = $this->establishSession($request, $user, $request->validated('device_name'));

        return (new UserResource($user->load('mitraProfile')))
            ->additional(array_filter(['token' => $token]))
            ->response();
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            // Mobile client authenticated with a bearer token — revoke just
            // this token, other devices/sessions stay logged in.
            $token->delete();
        } else {
            // Web SPA authenticated via cookie session.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('mitraProfile'));
    }

    /**
     * Authenticate `$user` the right way for the calling client: a cookie
     * session for the web SPA, or a Sanctum personal access token for
     * anything else (mobile app). Which one applies is decided by whether
     * Sanctum's stateful middleware actually ran for this request —
     * `EnsureFrontendRequestsAreStateful` only starts a session when the
     * request's Origin/Referer matches a configured frontend domain
     * (`SANCTUM_STATEFUL_DOMAINS`); a native mobile HTTP client never
     * matches that, so `$request->hasSession()` is false for it and calling
     * `$request->session()` at all would throw.
     *
     * @return string|null The plain-text token for mobile clients, null for web (nothing to return — the session cookie is already queued on the response).
     */
    private function establishSession(Request $request, User $user, ?string $deviceName): ?string
    {
        if ($request->hasSession()) {
            Auth::login($user);
            $request->session()->regenerate();

            return null;
        }

        return $user->createToken($deviceName ?: 'mobile')->plainTextToken;
    }
}
