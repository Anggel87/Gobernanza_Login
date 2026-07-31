<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class InternalUserController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::exists('roles', 'key_name')],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'active' => ['nullable', 'boolean'],
            'verified' => ['nullable', 'boolean'],
        ]);

        $password = $data['password'] ?? Str::random(12);
        $role = Role::where('key_name', $data['role'])->firstOrFail();

        $user = User::create([
            'role_id' => $role->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $password,
            'active' => $data['active'] ?? true,
            'email_verified_at' => ($data['verified'] ?? true) ? now() : null,
        ]);

        $response = [
            'user' => app(AuthController::class)->serializeUser($user->load('role')),
        ];

        if (! isset($data['password'])) {
            $response['temporary_password'] = $password;
        }

        return ApiResponse::success($response, 'Usuario creado en gobernanza.', 201);
    }
}
