<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Helpers\ApiResponse as Response;
use App\Http\Controllers\Controller;
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();
    
        if (!$user || !Hash::check($request->password, $user->password)) {
            return Response::error('Credenciales incorrectas.', 'AUTH03', 401);
        }
    
        if (!$user->active) {
            return Response::error('Usuario inactivo.', 'AUTH01', 403);
        }
    
        $clientApp = $request->attributes->get('client_app');
        $tokenName = $clientApp->slug . ':' . $request->device_name;
    
        $token = $user->createToken($tokenName)->plainTextToken;
    
        return Response::success('Login exitoso.', [
            'token' => $token,
            'user'  => $user->only(['id', 'name', 'email']),
        ]);
    }
}
