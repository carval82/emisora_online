<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\Song;
use App\Services\LocalMusicService;
use Illuminate\Http\Request;

class LocalMusicController extends Controller
{
    public function __construct(private LocalMusicService $local) {}

    public function index()
    {
        $this->local->ensureFolder();

        return view('admin.local.index', [
            'folderPath' => $this->local->folderPath(),
            'files' => $this->local->listAudioFiles(),
            'importedCount' => $this->local->localSongsCount(),
            'defaultPlaylist' => Playlist::getDefault(),
        ]);
    }

    public function scan(Request $request)
    {
        set_time_limit(0);

        $result = $this->local->scan(
            fetchOnline: $request->boolean('fetch_online'),
            addToPlaylist: $request->boolean('add_to_playlist', true),
        );

        $msg = "Escaneo completado: {$result['imported']} nuevas, {$result['skipped']} ya existían.";

        if (! empty($result['attached'])) {
            $msg .= " {$result['attached']} agregadas a la playlist.";
        }

        if ($result['failed'] > 0) {
            $msg .= " {$result['failed']} con error.";
        }

        return back()->with('success', $msg)->with('scan_errors', $result['errors']);
    }

    public function removeMissing()
    {
        $removed = $this->local->removeMissing();

        return back()->with('success', "Se quitaron {$removed} canciones cuyo archivo ya no existe.");
    }
}
