<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            'administrator' => 'administrador',
            'career_director' => 'director_carrera',
        ])->each(function (string $newKeyName, string $oldKeyName): void {
            $legacyRole = Role::where('key_name', $oldKeyName)->first();

            if (! $legacyRole) {
                return;
            }

            if (Role::where('key_name', $newKeyName)->exists()) {
                $legacyRole->delete();

                return;
            }

            $legacyRole->update(['key_name' => $newKeyName]);
        });

        collect([
            'alumno' => 'Alumno',
            'profesor' => 'Profesor',
            'tutor_academico' => 'Tutor Academico',
            'administrador' => 'Administrador Escolar',
            'director_carrera' => 'Director de Carrera',
        ])->each(fn (string $displayName, string $keyName) => Role::updateOrCreate(
            ['key_name' => $keyName],
            ['display_name' => $displayName],
        ));
    }
}
