<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\StationSetting;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    public function index()
    {
        $playlists = Playlist::withCount('songs')->latest()->get();

        return view('admin.playlists.index', compact('playlists'));
    }

    public function create()
    {
        return view('admin.playlists.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'shuffle' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            Playlist::where('is_default', true)->update(['is_default' => false]);
        }

        $playlist = Playlist::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'shuffle' => $request->boolean('shuffle'),
            'is_default' => $request->boolean('is_default'),
        ]);

        $station = StationSetting::current();
        if (! $station->current_playlist_id) {
            $station->update(['current_playlist_id' => $playlist->id]);
        }

        return redirect()->route('admin.playlists.edit', $playlist)
            ->with('success', 'Playlist creada. Agrega canciones.');
    }

    public function edit(Playlist $playlist)
    {
        $playlist->load('songs');
        $availableSongs = Song::where('is_active', true)
            ->whereNotIn('id', $playlist->songs->pluck('id'))
            ->orderBy('title')
            ->get();

        return view('admin.playlists.edit', compact('playlist', 'availableSongs'));
    }

    public function update(Request $request, Playlist $playlist)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'shuffle' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            Playlist::where('is_default', true)->where('id', '!=', $playlist->id)
                ->update(['is_default' => false]);
        }

        $playlist->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'shuffle' => $request->boolean('shuffle'),
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Playlist actualizada.');
    }

    public function addSong(Request $request, Playlist $playlist)
    {
        $validated = $request->validate([
            'song_id' => 'required|exists:songs,id',
        ]);

        if ($playlist->songs()->where('song_id', $validated['song_id'])->exists()) {
            return back()->with('error', 'La canción ya está en la playlist.');
        }

        $position = $playlist->songs()->count();
        $playlist->songs()->attach($validated['song_id'], ['position' => $position]);

        return back()->with('success', 'Canción agregada a la playlist.');
    }

    public function removeSong(Playlist $playlist, Song $song)
    {
        $playlist->songs()->detach($song->id);

        $playlist->songs()->orderByPivot('position')->get()->each(function ($s, $index) use ($playlist) {
            $playlist->songs()->updateExistingPivot($s->id, ['position' => $index]);
        });

        return back()->with('success', 'Canción removida de la playlist.');
    }

    public function setActive(Playlist $playlist)
    {
        StationSetting::current()->update(['current_playlist_id' => $playlist->id]);

        return back()->with('success', 'Playlist activa en la emisora.');
    }

    public function destroy(Playlist $playlist)
    {
        $station = StationSetting::current();

        if ($station->current_playlist_id === $playlist->id) {
            $next = Playlist::where('id', '!=', $playlist->id)->where('is_active', true)->first();
            $station->update(['current_playlist_id' => $next?->id]);
        }

        $playlist->delete();

        return redirect()->route('admin.playlists.index')
            ->with('success', 'Playlist eliminada.');
    }
}
