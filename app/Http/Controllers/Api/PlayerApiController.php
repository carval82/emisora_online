<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\StationSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlayerApiController extends Controller
{
    public function localAudio(Song $song): BinaryFileResponse
    {
        abort_unless($song->isLocal(), 404);

        $path = $song->absolute_path;

        abort_if(! $path, 404, 'Archivo no encontrado en la carpeta');

        return response()->file($path, [
            'Content-Type' => $this->guessMime($path),
        ]);
    }

    private function guessMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp3', 'mpeg' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'm4a', 'mp4', 'aac' => 'audio/mp4',
            'flac' => 'audio/flac',
            default => 'application/octet-stream',
        };
    }

    public function station()
    {
        $station = StationSetting::current()->load('currentPlaylist');

        return response()->json([
            'name' => $station->name,
            'slogan' => $station->slogan,
            'logo_url' => $station->logo_url,
            'is_live' => $station->is_live,
            'host_name' => $station->live_host_name,
            'playlist' => $station->currentPlaylist?->name,
        ]);
    }

    public function queue()
    {
        $station = StationSetting::current();
        $playlist = $station->currentPlaylist
            ?? Playlist::getDefault();

        if (! $playlist) {
            return response()->json(['songs' => [], 'shuffle' => false]);
        }

        $songs = $playlist->activeSongs()->get()->map(fn ($song) => [
            'id' => $song->id,
            'title' => $song->title,
            'artist' => $song->artist,
            'album' => $song->album,
            'url' => $song->url,
            'duration' => $song->duration,
        ]);

        if ($playlist->shuffle) {
            $songs = $songs->shuffle()->values();
        }

        return response()->json([
            'songs' => $songs,
            'shuffle' => $playlist->shuffle,
            'playlist' => $playlist->name,
        ]);
    }

    public function messages()
    {
        $messages = Message::where('is_approved', true)
            ->latest()
            ->take(50)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'sender_name' => $m->sender_name,
                'content' => $m->content,
                'created_at' => $m->created_at->diffForHumans(),
            ]);

        return response()->json(['messages' => $messages]);
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'sender_name' => 'required|string|max:100',
            'content' => 'required|string|max:500',
        ]);

        $message = Message::create([
            'sender_name' => $validated['sender_name'],
            'content' => $validated['content'],
            'is_approved' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'sender_name' => $message->sender_name,
                'content' => $message->content,
                'created_at' => 'ahora',
            ],
        ]);
    }
}
