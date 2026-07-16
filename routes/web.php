<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\PlaylistController;
use App\Http\Controllers\Admin\LiveController;
use App\Http\Controllers\Admin\LocalMusicController;
use App\Http\Controllers\Admin\SongController;
use App\Http\Controllers\PlayerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlayerController::class, 'index'])->name('player');
Route::redirect('/app', '/app/');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('songs', SongController::class)->except(['show', 'edit', 'update']);
        Route::post('songs/upload', [SongController::class, 'upload'])->name('songs.upload');
        Route::patch('songs/{song}/toggle', [SongController::class, 'toggle'])->name('songs.toggle');

        Route::resource('playlists', PlaylistController::class)->except(['show']);
        Route::post('playlists/{playlist}/songs', [PlaylistController::class, 'addSong'])->name('playlists.songs.add');
        Route::delete('playlists/{playlist}/songs/{song}', [PlaylistController::class, 'removeSong'])->name('playlists.songs.remove');
        Route::post('playlists/{playlist}/activate', [PlaylistController::class, 'setActive'])->name('playlists.activate');

        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::patch('messages/{message}/read', [MessageController::class, 'markRead'])->name('messages.read');
        Route::patch('messages/{message}/approval', [MessageController::class, 'toggleApproval'])->name('messages.approval');
        Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
        Route::put('station', [MessageController::class, 'updateStation'])->name('station.update');

        Route::get('live', [LiveController::class, 'index'])->name('live.index');
        Route::post('live/start', [LiveController::class, 'start'])->name('live.start');
        Route::post('live/stop', [LiveController::class, 'stop'])->name('live.stop');
        Route::post('live/chunk', [LiveController::class, 'chunk'])->name('live.chunk');
        Route::get('live/heartbeat', [LiveController::class, 'heartbeat'])->name('live.heartbeat');

        Route::get('carpeta', [LocalMusicController::class, 'index'])->name('local.index');
        Route::post('carpeta/escanear', [LocalMusicController::class, 'scan'])->name('local.scan');
        Route::post('carpeta/limpiar', [LocalMusicController::class, 'removeMissing'])->name('local.clean');
    });
});
