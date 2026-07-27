<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Models\ClientApp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClientApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Client-Id');
        $apiSecret = $request->header('X-Client-Secret');

        if (! $apiKey || ! $apiSecret) {
            return ApiResponse::error('Credenciales de cliente requeridas.', 'AUTH07', 401);
        }

        $clientApp = ClientApp::where('api_key', $apiKey)->first();

        if (! $clientApp || ! Hash::check($apiSecret, $clientApp->api_secret)) {
            return ApiResponse::error('Aplicacion no autorizada.', 'AUTH07', 401);
        }

        if (! $clientApp->active) {
            return ApiResponse::error('Aplicacion deshabilitada.', 'AUTH08', 403);
        }

        $clientApp->update(['last_used_at' => now()]);
        $request->attributes->set('client_app', $clientApp);

        return $next($request);
    }
}
