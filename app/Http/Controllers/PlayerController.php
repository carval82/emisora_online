<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\StationSetting;

class PlayerController extends Controller
{
    public function index()
    {
        $station = StationSetting::current()->load('currentPlaylist');

        $messages = Message::where('is_approved', true)
            ->latest()
            ->take(20)
            ->get();

        return view('player.index', compact('station', 'messages'));
    }
}
