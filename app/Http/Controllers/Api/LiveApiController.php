<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LiveStreamService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        return response()->json([
            'is_live' => true,
            'chunks' => $this->live->getChunksAfter($after, $limit),
            'latest_index' => $this->live->getLatestIndex(),
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
        $binary = $this->live->packChunksAfter($after);

        return response($binary, 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Live-Active' => '1',
            'X-Latest-Index' => (string) $this->live->getLatestIndex(),
        ]);
    }

    public function stream(Request $request): StreamedResponse
    {
        if (! $this->live->isActive()) {
            abort(404);
        }

        $path = $this->live->getStreamPath();
        $liveEdge = $request->boolean('live', false);
        $startPos = max(0, (int) $request->query('pos', 0));

        return response()->stream(function () use ($path, $startPos, $liveEdge) {
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }

            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            @ini_set('implicit_flush', '1');
            @set_time_limit(0);

            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            if ($liveEdge) {
                $init = $this->live->getInitBinary();
                if ($init) {
                    echo $init;
                    flush();
                }
                clearstatcache(true, $path);
                $pos = is_readable($path) ? (int) filesize($path) : 0;
            } else {
                $pos = $startPos;
            }

            $idleTicks = 0;

            while ($idleTicks < 600) {
                if (connection_aborted()) {
                    break;
                }

                clearstatcache(true, $path);
                $size = is_readable($path) ? (int) filesize($path) : 0;

                if ($pos < $size) {
                    $handle = fopen($path, 'rb');
                    if ($handle) {
                        fseek($handle, $pos);

                        while ($pos < $size && ! connection_aborted()) {
                            $chunk = fread($handle, 32768);
                            if ($chunk === false || $chunk === '') {
                                break;
                            }

                            echo $chunk;
                            $pos += strlen($chunk);
                            flush();
                        }

                        fclose($handle);
                    }

                    $idleTicks = 0;
                } else {
                    if (! $this->live->isActive()) {
                        $idleTicks++;
                    }

                    usleep(80_000);
                }
            }
        }, 200, [
            'Content-Type' => 'audio/webm',
            'Cache-Control' => 'no-cache, no-store',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Live-Active' => '1',
        ]);
    }
}
