<?php

namespace App\Http\Controllers;

use App\Models\ClientApp;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return $this->redirectWithCodeResponse(
            $authController,
            $user,
            $context['clientApp'],
            $context['returnUrl'],
        );
    }

    public function logout(Request $request)
    {
        $context = $this->context($request);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away($context['returnUrl']);
    }

    private function context(Request $request): array
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'exists:client_apps,api_key'],
            'redirect_uri' => ['nullable', 'url'],
            'returnUrl' => ['nullable', 'url'],
            'return_url' => ['nullable', 'url'],
        ]);

        $clientApp = ClientApp::where('api_key', $data['client_id'])->where('active', true)->firstOrFail();
        $returnUrl = $data['returnUrl'] ?? $data['return_url'] ?? $data['redirect_uri'] ?? null;

        if (! $returnUrl || ! $this->isAllowedRedirectUri($clientApp, $returnUrl)) {
            throw new HttpResponseException(response()->view('errors.governance-client', [
                'message' => 'Esta aplicacion no esta autorizada para abrir el login de gobernanza.',
            ], 403));
        }

        return [
            'clientApp' => $clientApp,
            'clientId' => $data['client_id'],
            'redirectUri' => $data['redirect_uri'] ?? null,
            'returnUrl' => $returnUrl,
        ];
    }

    private function redirectWithCodeResponse(
        AuthController $authController,
        User $user,
        ClientApp $clientApp,
        string $returnUrl,
    )
    {
        $code = $authController->issueRedirectCode($user, $clientApp, $returnUrl);

        return redirect()->away($this->appendQuery($returnUrl, ['code' => $code]));
    }

    private function appendQuery(string $url, array $query): string
    {
        [$urlWithoutFragment, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        [$baseUrl, $existingQueryString] = array_pad(explode('?', $urlWithoutFragment, 2), 2, '');
        $existingQuery = [];

        if ($existingQueryString !== '') {
            parse_str($existingQueryString, $existingQuery);
        }

        $queryString = http_build_query(array_merge($existingQuery, $query));
        $fragmentSuffix = $fragment ? '#'.$fragment : '';

        return $baseUrl.'?'.$queryString.$fragmentSuffix;
    }

    private function isAllowedRedirectUri(ClientApp $clientApp, string $redirectUri): bool
    {
        $allowedRedirectUris = $clientApp->allowed_redirect_uris ?? [];

        return collect($allowedRedirectUris)->contains(function (string $allowedUri) use ($redirectUri): bool {
            return rtrim($allowedUri, '/') === rtrim($redirectUri, '/')
                || $this->originFromUrl($allowedUri) === $this->originFromUrl($redirectUri);
        });
    }

    private function originFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
