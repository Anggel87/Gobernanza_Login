<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClientApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Client-Id');
        $apiSecret = $request->header('X-Client-Secret');

        if (!$apiKey || !$apiSecret) {
            return apiError('Credenciales de cliente requeridas.', 'AUTH07', 401);
        }

        $clientApp = ClientApp::where('api_key', $apiKey)->first();

        if (!$clientApp || !Hash::check($apiSecret, $clientApp->api_secret)) {
            return apiError('Aplicación no autorizada.', 'AUTH07', 401);
        }

        if (!$clientApp->active) {
            return apiError('Aplicación deshabilitada.', 'AUTH08', 403);
        }

        $clientApp->update(['last_used_at' => now()]);

        // Lo dejamos disponible para el controller (para nombrar el token con la app)
        $request->attributes->set('client_app', $clientApp);

        return $next($request);
    }
}