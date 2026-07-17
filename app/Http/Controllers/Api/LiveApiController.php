<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LiveStreamService;
use Illuminate\Http\Request;

class LiveApiController extends Controller
{
    public function __construct(private LiveStreamService $live) {}

    public function status()
    {
        return response()->json($this->live->getStatus());
    }

    public function chunks(Request $request)
    {
        if (! $this->live->isActive()) {
            return response()->json(['chunks' => [], 'is_live' => false]);
        }

        $after = (int) $request->query('after', -1);
        $limit = min(max((int) $request->query('limit', 6), 1), 12);
        $latest = $this->live->getLatestIndex();

        return response()->json([
            'is_live' => true,
            'chunks' => $this->live->getChunksAfter($after, $limit),
            'latest_index' => $latest,
            'reset' => $after > $latest,
        ]);
    }

    public function audio(int $index)
    {
        if (! $this->live->isActive()) {
            abort(404);
        }

        $data = $this->live->getPlayableChunkBinary($index);

        if (! $data) {
            abort(404);
        }

        return response($data, 200, [
            'Content-Type' => $this->live->getChunkMime($index),
            'Cache-Control' => 'no-cache, no-store',
            'Accept-Ranges' => 'none',
        ]);
    }

    /** Espera el siguiente chunk y devuelve su URL (simple, para el oyente). */
    public function next(Request $request)
    {
        if (! $this->live->isActive()) {
            return response('', 204)->header('X-Live-Active', '0');
        }

        $after = (int) $request->query('after', -1);
        $wait = min(max((int) $request->query('wait', 3000), 500), 8000);
        $index = $this->waitForNextChunk($after, $wait);

        if ($index === null) {
            return response()->json([
                'is_live' => true,
                'index' => $after,
            ]);
        }

        $status = $this->live->getStatus();

        return response()->json([
            'is_live' => true,
            'index' => $index,
            'url' => url("/api/live/audio/{$index}"),
            'host_name' => $status['host_name'],
        ]);
    }

    public function init()
    {
        if (! $this->live->isActive()) {
            abort(404);
        }

        $data = $this->live->getInitBinary();

        if (! $data) {
            abort(404);
        }

        return response($data, 200, [
            'Content-Type' => 'audio/webm',
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }

    private function waitForNextChunk(int $after, int $waitMs): ?int
    {
        $deadline = microtime(true) + ($waitMs / 1000);

        do {
            $next = $after + 1;
            if ($this->live->getChunkPath($next)) {
                return $next;
            }

            $latest = $this->live->getLatestIndex();
            if ($latest > $after && $this->live->getChunkPath($latest)) {
                return $latest;
            }

            if (! $this->live->isActive()) {
                return null;
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        return null;
    }

    public function pack(Request $request)
    {
        if (! $this->live->isActive()) {
            return response('', 204)
                ->header('X-Live-Active', '0');
        }

        $after = (int) $request->query('after', -1);
        $latest = $this->live->getLatestIndex();
        $binary = $this->live->packChunksAfter($after);

        return response($binary, 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Live-Active' => '1',
            'X-Latest-Index' => (string) $latest,
            'X-Stream-Reset' => $after > $latest ? '1' : '0',
        ]);
    }

}
