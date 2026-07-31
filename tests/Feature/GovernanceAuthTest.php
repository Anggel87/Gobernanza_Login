<?php

use App\Models\ClientApp;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function clientApp(array $overrides = []): array
{
    $secret = 'secret-value';
    $app = ClientApp::create(array_merge([
        'name' => 'Portal Web',
        'slug' => 'portal-web',
        'api_key' => 'client-key',
        'api_secret' => Hash::make($secret),
        'active' => true,
        'allowed_redirect_uris' => ['http://localhost:4200', 'http://localhost:4200/auth/callback'],
    ], $overrides));

    return [$app, $secret];
}

function seedGovernanceRoles(): void
{
    collect([
        'alumno' => 'Alumno',
        'profesor' => 'Profesor',
        'tutor_academico' => 'Tutor Academico',
        'administrador' => 'Administrador Escolar',
        'director_carrera' => 'Director de Carrera',
    ])->each(fn (string $displayName, string $keyName) => Role::create([
        'key_name' => $keyName,
        'display_name' => $displayName,
    ]));
}

test('mobile client can login and receive bearer token', function () {
    [$client, $secret] = clientApp();
    $user = User::factory()->create();

    $response = $this
        ->withHeaders([
            'X-Client-Id' => $client->api_key,
            'X-Client-Secret' => $secret,
        ])
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'android',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonStructure(['data' => ['token']]);
});

test('api login rejects invalid client credentials', function () {
    clientApp();
    $user = User::factory()->create();

    $this
        ->withHeaders([
            'X-Client-Id' => 'client-key',
            'X-Client-Secret' => 'wrong-secret',
        ])
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'AUTH07');
});

test('authenticated token can be refreshed and logged out', function () {
    [$client, $secret] = clientApp();
    $user = User::factory()->create();

    $token = $this
        ->withHeaders([
            'X-Client-Id' => $client->api_key,
            'X-Client-Secret' => $secret,
        ])
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->json('data.token');

    $newToken = $this
        ->withToken($token)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->json('data.token');

    expect($newToken)->not->toBe($token);

    $this
        ->withToken($newToken)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();
});

test('authenticated token can resolve current user', function () {
    [$client, $secret] = clientApp();
    seedGovernanceRoles();

    $role = Role::where('key_name', 'alumno')->first();
    $user = User::factory()->create(['role_id' => $role->id]);

    $token = $this
        ->withHeaders([
            'X-Client-Id' => $client->api_key,
            'X-Client-Secret' => $secret,
        ])
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->json('data.token');

    $this
        ->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.user.role', 'alumno');
});

test('web popup auth screen can be rendered for active client', function () {
    [$client] = clientApp();

    $this
        ->get('/governance/auth?client_id='.$client->api_key.'&redirect_uri=http://localhost:4200/auth/callback')
        ->assertOk()
        ->assertSee('Bienvenido de nuevo')
        ->assertDontSee('Registrarse');
});

test('web popup auth screen rejects untrusted redirect uri', function () {
    [$client] = clientApp();

    $this
        ->get('/governance/auth?client_id='.$client->api_key.'&redirect_uri=http://evil.test/auth/callback')
        ->assertForbidden()
        ->assertSee('Acceso no autorizado');
});

test('trusted api client can create governance users by role', function () {
    [$client, $secret] = clientApp();
    seedGovernanceRoles();

    $response = $this
        ->withHeaders([
            'X-Client-Id' => $client->api_key,
            'X-Client-Secret' => $secret,
        ])
        ->postJson('/api/v1/internal/users', [
            'name' => 'Carlos Lopez',
            'email' => 'carlos.lopez@example.edu',
            'role' => 'profesor',
            'active' => true,
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'carlos.lopez@example.edu')
        ->assertJsonPath('data.user.role', 'profesor')
        ->assertJsonStructure(['data' => ['temporary_password']]);

    $this->assertDatabaseHas('users', [
        'email' => 'carlos.lopez@example.edu',
        'active' => true,
    ]);
});

test('internal user creation rejects unknown roles', function () {
    [$client, $secret] = clientApp();
    seedGovernanceRoles();

    $this
        ->withHeaders([
            'X-Client-Id' => $client->api_key,
            'X-Client-Secret' => $secret,
        ])
        ->postJson('/api/v1/internal/users', [
            'name' => 'Invalid Role',
            'email' => 'invalid.role@example.edu',
            'role' => 'unknown',
        ])
        ->assertUnprocessable();
});
