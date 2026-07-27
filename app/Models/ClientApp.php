<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClientApp extends Model
{
    protected $fillable = ['name', 'slug', 'api_key', 'api_secret', 'active', 'last_used_at'];

    protected $hidden = ['api_secret'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public static function generateCredentials(): array
    {
        return [
            'api_key' => Str::random(32),
            'api_secret' => Str::random(64),
        ];
    }
}
