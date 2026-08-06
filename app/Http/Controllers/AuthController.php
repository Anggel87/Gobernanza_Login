<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ClientApp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const REDIRECT_CODE_CACHE_PREFIX = 'governance_redirect_code:';

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::error('Credenciales incorrectas.', 'AUTH01', 401);
        }

        if (! $user->active) {
            return ApiResponse::error('Tu cuenta esta desactivada. Contacta al administrador.', 'AUTH03', 403);
        }

        if (! $user->email_verified_at) {
            return ApiResponse::error('Tu cuenta aun no ha sido verificada. Revisa tu correo.', 'AUTH04', 403);
        }

        $token = $this->issueToken(
            $user,
            $request->attributes->get('client_app'),
            $credentials['device_name'] ?? 'mobile'
        );

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->serializeUser($user->loadMissing('role')),
        ], 'Login exitoso.');
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token) {
            return ApiResponse::error('No hay una sesion activa.', 'AUTH05', 401);
        }

        $token->delete();

        return ApiResponse::success([], 'Sesion cerrada con exito.');
    }

    public function exchangeCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'client_id' => ['required', 'string', 'exists:client_apps,api_key'],
            'return_url' => ['required', 'url'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ]);

        $payload = Cache::pull(self::REDIRECT_CODE_CACHE_PREFIX.$data['code']);

        if (! is_array($payload)) {
            return ApiResponse::error('Codigo de autenticacion invalido o expirado.', 'AUTH09', 401);
        }

        $clientApp = ClientApp::where('api_key', $data['client_id'])->where('active', true)->first();

        if (! $clientApp || (int) $payload['client_app_id'] !== $clientApp->id) {
            return ApiResponse::error('Codigo de autenticacion invalido o expirado.', 'AUTH09', 401);
        }

        if (rtrim((string) $payload['return_url'], '/') !== rtrim($data['return_url'], '/')) {
            return ApiResponse::error('Codigo de autenticacion invalido o expirado.', 'AUTH09', 401);
        }

        $user = User::with('role')->find($payload['user_id']);

        if (! $user || ! $user->active) {
            return ApiResponse::error('Tu cuenta esta desactivada. Contacta al administrador.', 'AUTH03', 403);
        }

        if (! $user->email_verified_at) {
            return ApiResponse::error('Tu cuenta aun no ha sido verificada. Revisa tu correo.', 'AUTH04', 403);
        }

        return ApiResponse::success([
            'token' => $this->issueToken($user, $clientApp, $data['device_name'] ?? 'web-redirect'),
            'token_type' => 'Bearer',
            'user' => $this->serializeUser($user),
        ], 'Login exitoso.');
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();

        if (! $user || ! $currentToken) {
            return ApiResponse::error('Tu sesion ha expirado.', 'AUTH05', 401);
        }

        $tokenName = $currentToken->name;
        $currentToken->delete();

        return ApiResponse::success([
            'token' => $user->createToken($tokenName)->plainTextToken,
            'token_type' => 'Bearer',
        ], 'Token renovado con exito.');
    }

    public function me(Request $request)
    {
        return ApiResponse::success([
            'user' => $this->serializeUser($request->user()->loadMissing('role')),
        ], 'Usuario autenticado.');
    }

    public function issueToken(User $user, ?ClientApp $clientApp = null, string $deviceName = 'web'): string
    {
        $clientSlug = $clientApp?->slug ?? 'governance';
        $safeDeviceName = str($deviceName)->slug()->limit(80, '')->toString() ?: 'device';

        return $user->createToken($clientSlug.':'.$safeDeviceName)->plainTextToken;
    }

    public function issueRedirectCode(User $user, ClientApp $clientApp, string $returnUrl): string
    {
        $code = Str::random(64);

        Cache::put(self::REDIRECT_CODE_CACHE_PREFIX.$code, [
            'user_id' => $user->id,
            'client_app_id' => $clientApp->id,
            'return_url' => rtrim($returnUrl, '/'),
        ], now()->addMinutes(2));

        return $code;
    }

    public function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->key_name,
        ];
    }
}
