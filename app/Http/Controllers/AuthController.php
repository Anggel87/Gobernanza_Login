<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ClientApp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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
