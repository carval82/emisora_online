<?php

use App\Http\Controllers\Api\BroadcasterApiController;
use App\Http\Controllers\Api\LiveApiController;
use App\Http\Controllers\Api\PlayerApiController;
use Illuminate\Support\Facades\Route;

Route::get('/station', [PlayerApiController::class, 'station']);
Route::get('/queue', [PlayerApiController::class, 'queue']);
Route::get('/messages', [PlayerApiController::class, 'messages']);
Route::post('/messages', [PlayerApiController::class, 'sendMessage']);
Route::get('/local-audio/{song}', [PlayerApiController::class, 'localAudio'])->name('api.local.audio');

Route::get('/live/status', [LiveApiController::class, 'status']);
Route::get('/live/chunks', [LiveApiController::class, 'chunks']);
Route::get('/live/next', [LiveApiController::class, 'next']);
Route::get('/live/pack', [LiveApiController::class, 'pack']);
Route::get('/live/init', [LiveApiController::class, 'init']);
Route::get('/live/audio/{index}', [LiveApiController::class, 'audio'])->where('index', '[0-9]+');

Route::prefix('broadcaster')->group(function () {
    Route::post('/login', [BroadcasterApiController::class, 'login']);
    Route::middleware('broadcast.token')->group(function () {
        Route::get('/status', [BroadcasterApiController::class, 'status']);
        Route::post('/start', [BroadcasterApiController::class, 'start']);
        Route::post('/stop', [BroadcasterApiController::class, 'stop']);
        Route::post('/chunk', [BroadcasterApiController::class, 'chunk']);
    });
});
