<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('password');
        });

        Schema::table('client_apps', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('slug')->unique()->after('name');
            $table->string('api_key', 80)->unique()->after('slug');
            $table->string('api_secret')->after('api_key');
            $table->boolean('active')->default(true)->after('api_secret');
            $table->timestamp('last_used_at')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('client_apps', function (Blueprint $table) {
            $table->dropColumn(['name', 'slug', 'api_key', 'api_secret', 'active', 'last_used_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
