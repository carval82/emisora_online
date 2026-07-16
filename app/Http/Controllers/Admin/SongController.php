<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\Song;
use App\Services\SongMetadataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SongController extends Controller
{
    public function __construct(private SongMetadataService $metadata) {}

    public function index()
    {
        $songs = Song::latest()->paginate(15);

        return view('admin.songs.index', compact('songs'));
    }

    public function create()
    {
        $defaultPlaylist = Playlist::getDefault();

        return view('admin.songs.create', compact('defaultPlaylist'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'artist' => 'nullable|string|max:255',
            'album' => 'nullable|string|max:255',
            'audio' => 'required|file|mimes:mp3,mpeg,wav,ogg,m4a,aac,flac,mp4|max:40960',
            'fetch_online' => 'boolean',
            'add_to_playlist' => 'boolean',
        ]);

        $file = $request->file('audio');
        $fetchOnline = $request->boolean('fetch_online', true);
        $meta = $this->metadata->extract($file, $fetchOnline);

        $song = $this->saveSong($file, [
            'title' => $validated['title'] ?: $meta['title'],
            'artist' => $validated['artist'] ?: $meta['artist'],
            'album' => $validated['album'] ?: $meta['album'],
            'duration' => $meta['duration'],
        ]);

        if ($request->boolean('add_to_playlist', true)) {
            $this->addToDefaultPlaylist($song);
        }

        return redirect()->route('admin.songs.index')
            ->with('success', "Canción \"{$song->title}\" subida correctamente.");
    }

    public function upload(Request $request)
    {
        set_time_limit(120);

        $validated = $request->validate([
            'audio' => 'required|file|mimes:mp3,mpeg,wav,ogg,m4a,aac,flac,mp4|max:40960',
            'fetch_online' => 'boolean',
            'add_to_playlist' => 'boolean',
        ]);

        $file = $request->file('audio');
        $fetchOnline = $request->boolean('fetch_online', true);

        try {
            $meta = $this->metadata->extract($file, $fetchOnline);

            $song = $this->saveSong($file, $meta);

            if ($request->boolean('add_to_playlist', true)) {
                $this->addToDefaultPlaylist($song);
            }

            return response()->json([
                'success' => true,
                'song' => [
                    'id' => $song->id,
                    'title' => $song->title,
                    'artist' => $song->artist,
                    'album' => $song->album,
                    'duration' => $song->formatted_duration,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ], 422);
        }
    }

    public function destroy(Song $song)
    {
        Storage::disk('public')->delete($song->file_path);
        $song->delete();

        return redirect()->route('admin.songs.index')
            ->with('success', 'Canción eliminada.');
    }

    public function toggle(Song $song)
    {
        $song->update(['is_active' => ! $song->is_active]);

        return back()->with('success', 'Estado de la canción actualizado.');
    }

    private function saveSong($file, array $meta): Song
    {
        $path = $file->store('songs', 'public');

        return Song::create([
            'title' => $meta['title'],
            'artist' => $meta['artist'],
            'album' => $meta['album'],
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'duration' => $meta['duration'] ?? 0,
        ]);
    }

    private function addToDefaultPlaylist(Song $song): void
    {
        $playlist = Playlist::getDefault();

        if (! $playlist || $playlist->songs()->where('song_id', $song->id)->exists()) {
            return;
        }

        $position = $playlist->songs()->count();
        $playlist->songs()->attach($song->id, ['position' => $position]);
    }
}
