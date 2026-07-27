<?php

namespace App\Http\Controllers;

use App\Models\ClientApp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GovernanceAuthController extends Controller
{
    public function show(Request $request)
    {
        $context = $this->context($request);

        return view('governance.auth', $context);
    }

    public function login(Request $request, AuthController $authController)
    {
        $context = $this->context($request);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Credenciales incorrectas.'])->withInput();
        }

        if (! $user->active) {
            return back()->withErrors(['email' => 'Tu cuenta esta desactivada. Contacta al administrador.'])->withInput();
        }

        if (! $user->email_verified_at) {
            return back()->withErrors(['email' => 'Tu cuenta aun no ha sido verificada. Revisa tu correo.'])->withInput();
        }

        return $this->tokenResponse($request, $authController, $user, $context['clientApp']);
    }

    private function context(Request $request): array
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'exists:client_apps,api_key'],
            'redirect_uri' => ['nullable', 'url'],
        ]);

        $clientApp = ClientApp::where('api_key', $data['client_id'])->where('active', true)->firstOrFail();

        return [
            'clientApp' => $clientApp,
            'clientId' => $data['client_id'],
            'redirectUri' => $data['redirect_uri'] ?? null,
        ];
    }

    private function tokenResponse(Request $request, AuthController $authController, User $user, ClientApp $clientApp)
    {
        $payload = [
            'token' => $authController->issueToken($user, $clientApp, 'web-popup'),
            'token_type' => 'Bearer',
            'user' => $authController->serializeUser($user),
        ];

        return view('governance.token', [
            'payload' => $payload,
            'redirectUri' => $request->input('redirect_uri'),
        ]);
    }
}
