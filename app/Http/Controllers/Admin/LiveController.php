<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StationSetting;
use App\Services\LiveStreamService;
use Illuminate\Http\Request;

class LiveController extends Controller
{
    public function __construct(private LiveStreamService $live) {}

    public function index()
    {
        $station = StationSetting::current();
        $status = $this->live->getStatus();

        if ($status['is_live'] && $status['latest_index'] < 0) {
            $this->live->stop();
            $status = $this->live->getStatus();
        }

        $library = \App\Models\Song::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'artist', 'album', 'source', 'duration'])
            ->map(fn ($song) => [
                'id' => $song->id,
                'title' => $song->title,
                'artist' => $song->artist,
                'album' => $song->album,
                'url' => $song->url,
                'source' => $song->source,
                'duration' => $song->duration,
            ]);

        return view('admin.live.index', compact('station', 'status', 'library'));
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'host_name' => 'nullable|string|max:100',
        ]);

        $this->live->start($validated['host_name'] ?? auth()->user()?->name);

        return response()->json([
            'success' => true,
            'message' => 'Transmisión en vivo iniciada',
            'status' => $this->live->getStatus(),
        ]);
    }

    public function stop()
    {
        $this->live->stop();

        return response()->json([
            'success' => true,
            'message' => 'Transmisión finalizada',
        ]);
    }

    public function chunk(Request $request)
    {
        if (! $this->live->isActive()) {
            return response()->json(['error' => 'No hay transmisión activa'], 403);
        }

        $mime = $request->header('Content-Type', 'audio/webm');
        $mime = explode(';', $mime)[0];
        $content = $request->getContent();

        if (strlen($content) < 10 && $request->hasFile('chunk')) {
            $file = $request->file('chunk');
            if (! $file->isValid()) {
                return response()->json(['error' => 'Archivo de audio inválido'], 422);
            }
            $mime = $request->input('mime', $file->getMimeType() ?: 'audio/webm');
            $content = file_get_contents($file->getRealPath());
        }

        if (strlen($content) < 10) {
            return response()->json(['error' => 'No se recibió audio'], 422);
        }

        $index = $this->live->addChunk($content, $mime);

        return response()->json([
            'success' => true,
            'index' => $index,
            'size' => strlen($content),
        ]);
    }

    public function heartbeat()
    {
        return response()->json([
            'active' => $this->live->isActive(),
            'status' => $this->live->getStatus(),
        ]);
    }
}
