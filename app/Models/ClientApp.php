<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClientApp extends Model
{
    protected $fillable = ['name', 'slug', 'api_key', 'api_secret', 'active'];

    protected $hidden = ['api_secret'];

    public static function generateCredentials(): array
    {
        return [
            'api_key'    => Str::random(32),
            'api_secret' => Str::random(64),
        ];
    }
}