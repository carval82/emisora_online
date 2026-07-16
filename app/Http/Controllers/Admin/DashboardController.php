<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\StationSetting;
use App\Support\LanHelper;

class DashboardController extends Controller
{
    public function index()
    {
        $station = StationSetting::current();
        $lanPort = config('emisora.lan_port', 8000);

        return view('admin.dashboard', [
            'station' => $station,
            'stats' => [
                'songs' => Song::count(),
                'playlists' => Playlist::count(),
                'messages' => Message::where('is_read', false)->count(),
                'unread_messages' => Message::where('is_read', false)->count(),
            ],
            'recentMessages' => Message::latest()->take(5)->get(),
            'recentSongs' => Song::latest()->take(5)->get(),
            'lanAddresses' => LanHelper::addresses(),
            'lanPort' => $lanPort,
        ]);
    }
}
