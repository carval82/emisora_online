<?php

namespace Database\Seeders;

use App\Models\Playlist;
use App\Models\StationSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'pcapacho24@gmail.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('anaval33'),
            ]
        );

        $playlist = Playlist::updateOrCreate(
            ['name' => 'Programación Principal'],
            [
                'description' => 'Playlist por defecto de la emisora',
                'is_active' => true,
                'is_default' => true,
                'shuffle' => true,
            ]
        );

        StationSetting::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Mi Emisora Online',
                'slogan' => 'La mejor música, las 24 horas',
                'is_live' => false,
                'current_playlist_id' => $playlist->id,
            ]
        );
    }
}
