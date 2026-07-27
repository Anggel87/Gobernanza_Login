<?php

use App\Models\ClientApp;
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
    ], $overrides));

    return [$app, $secret];
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

test('web popup auth screen can be rendered for active client', function () {
    [$client] = clientApp();

    $this
        ->get('/governance/auth?client_id='.$client->api_key)
        ->assertOk()
        ->assertSee('Acceso de gobernanza')
        ->assertDontSee('Registrarse');
});
