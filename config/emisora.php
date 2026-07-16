<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Carpeta de música local
    |--------------------------------------------------------------------------
    |
    | Ruta en el disco donde puedes soltar tus archivos de audio. La emisora
    | escanea esta carpeta e importa las canciones sin copiarlas: se quedan
    | en su ubicación y se reproducen directamente desde ahí.
    |
    | Por defecto es la carpeta "musica" dentro del proyecto, pero puedes
    | apuntar a cualquier ruta del disco con LOCAL_MUSIC_PATH en el .env,
    | por ejemplo: LOCAL_MUSIC_PATH=D:\MiMusica
    |
    */

    'music_folder' => env('LOCAL_MUSIC_PATH', base_path('musica')),

    'audio_extensions' => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'mp4'],

    /*
    |--------------------------------------------------------------------------
    | Puerto para acceso en red local (LAN)
    |--------------------------------------------------------------------------
    */
    'lan_port' => (int) env('LAN_PORT', 8000),

];
