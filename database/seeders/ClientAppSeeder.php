<?php

namespace Database\Seeders;

use App\Models\ClientApp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClientAppSeeder extends Seeder
{
    public function run(): void
    {
        ClientApp::updateOrCreate(
            ['slug' => 'governance-web'],
            [
                'name' => 'Gobernanza Web',
                'api_key' => env('GOVERNANCE_WEB_CLIENT_ID', 'governance-web-local'),
                'api_secret' => Hash::make(env('GOVERNANCE_WEB_CLIENT_SECRET', 'governance-web-secret')),
                'active' => true,
            ],
        );

        ClientApp::updateOrCreate(
            ['slug' => 'governance-mobile'],
            [
                'name' => 'Gobernanza Mobile',
                'api_key' => env('GOVERNANCE_MOBILE_CLIENT_ID', 'governance-mobile-local'),
                'api_secret' => Hash::make(env('GOVERNANCE_MOBILE_CLIENT_SECRET', 'governance-mobile-secret')),
                'active' => true,
            ],
        );
    }
}
