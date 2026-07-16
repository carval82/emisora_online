<?php

namespace App\Services;

use App\Models\StationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class LiveStreamService
{
    private const MAX_CHUNKS = 240;

    /** Máximo de segmentos recientes al conectar un oyente nuevo (evita descargar todo el historial). */
    private const JOIN_CATCHUP_CHUNKS = 4;

    private const WEBM_CLUSTER = "\x1F\x43\xB6\x75";

    private string $chunkPath;

    private string $streamPath;

    public function __construct()
    {
        $this->chunkPath = storage_path('app/live/chunks');
        $this->streamPath = storage_path('app/live/stream.webm');
    }

    public function start(?string $hostName = null): void
    {
        $station = StationSetting::current();

        $this->clearChunks();
        File::ensureDirectoryExists($this->chunkPath);

        Cache::put('live:active', true, now()->addHours(6));
        Cache::put('live:index', -1, now()->addHours(6));
        Cache::put('live:host', $hostName ?: 'Locutor en vivo', now()->addHours(6));
        Cache::put('live:started_at', now()->toIso8601String(), now()->addHours(6));
        Cache::put('live:stream_bytes', 0, now()->addHours(6));

        $station->update([
            'is_live' => true,
            'live_host_name' => $hostName,
            'live_started_at' => now(),
        ]);
    }

    public function stop(): void
    {
        $this->clearChunks();
        Cache::forget('live:active');
        Cache::forget('live:index');
        Cache::forget('live:host');
        Cache::forget('live:started_at');
        Cache::forget('live:last_chunk_at');
        Cache::forget('live:init');
        Cache::forget('live:stream_bytes');

        StationSetting::current()->update([
            'is_live' => false,
            'live_host_name' => null,
            'live_started_at' => null,
        ]);
    }

    public function addChunk(string $binaryData, string $mime): int
    {
        File::ensureDirectoryExists($this->chunkPath);

        $index = (int) Cache::get('live:index', -1) + 1;
        $extension = str_contains($mime, 'ogg') ? 'ogg' : (str_contains($mime, 'mp4') ? 'm4a' : 'webm');

        file_put_contents("{$this->chunkPath}/{$index}.{$extension}", $binaryData);

        if ($index === 0) {
            $init = $this->extractWebmInit($binaryData);
            file_put_contents("{$this->chunkPath}/init.{$extension}", $init);
            Cache::put('live:init', $init, now()->addHours(6));
        }

        Cache::put('live:chunk_meta', array_merge(Cache::get('live:chunk_meta', []), [
            $index => ['mime' => $mime, 'ext' => $extension, 'size' => strlen($binaryData)],
        ]), now()->addMinutes(15));

        Cache::put('live:index', $index, now()->addHours(6));
        Cache::put('live:last_chunk_at', now()->toIso8601String(), now()->addHours(6));

        $this->appendToStream($index);

        $this->pruneOldChunks($index);

        return $index;
    }

    public function getChunkPath(int $index): ?string
    {
        $meta = Cache::get('live:chunk_meta', [])[$index] ?? null;

        if ($meta) {
            $path = "{$this->chunkPath}/{$index}.{$meta['ext']}";
            if (file_exists($path)) {
                return $path;
            }
        }

        foreach (['webm', 'ogg', 'm4a'] as $ext) {
            $path = "{$this->chunkPath}/{$index}.{$ext}";
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function getChunkMime(int $index): string
    {
        return Cache::get('live:chunk_meta', [])[$index]['mime'] ?? 'audio/webm';
    }

    public function getStreamPath(): string
    {
        return $this->streamPath;
    }

    public function getStreamBytes(): int
    {
        if (is_readable($this->streamPath)) {
            clearstatcache(true, $this->streamPath);

            return (int) filesize($this->streamPath);
        }

        return (int) Cache::get('live:stream_bytes', 0);
    }

    public function getInitBinary(): ?string
    {
        return $this->getInitSegment();
    }

    /** WebM reproducible en el elemento &lt;audio&gt; del navegador. */
    public function getPlayableChunkBinary(int $index): ?string
    {
        $data = $this->readChunkBinary($index);

        if ($data === null) {
            return null;
        }

        // Segmento completo (FFmpeg segment muxer)
        if (str_starts_with($data, "\x1a\x45\xdf\xa3")) {
            return $data;
        }

        $init = $this->getInitSegment();
        if (! $init) {
            return $data;
        }

        return $init.$this->extractWebmMedia($data);
    }

    public function isActive(): bool
    {
        return (bool) Cache::get('live:active', false)
            || StationSetting::current()->is_live;
    }

    public function getStatus(): array
    {
        $station = StationSetting::current();

        return [
            'is_live' => $this->isActive(),
            'host_name' => Cache::get('live:host') ?: $station->live_host_name,
            'started_at' => Cache::get('live:started_at') ?: $station->live_started_at?->toIso8601String(),
            'latest_index' => (int) Cache::get('live:index', -1),
        ];
    }

    public function getChunksAfter(int $after, int $limit = 0): array
    {
        $latest = (int) Cache::get('live:index', -1);
        $meta = Cache::get('live:chunk_meta', []);
        $chunks = [];

        $start = $after + 1;

        if ($start <= 0 && $this->getChunkPath(0)) {
            $chunks[] = [
                'index' => 0,
                'url' => url('/api/live/audio/0'),
                'mime' => $meta[0]['mime'] ?? 'audio/webm',
            ];
            $start = 1;
        }

        for ($i = $start; $i <= $latest; $i++) {
            if ($this->getChunkPath($i)) {
                $chunks[] = [
                    'index' => $i,
                    'url' => url("/api/live/audio/{$i}"),
                    'mime' => $meta[$i]['mime'] ?? 'audio/webm',
                ];
            }
        }

        if ($limit > 0 && count($chunks) > $limit) {
            $chunks = array_slice($chunks, -$limit);
        }

        return $chunks;
    }

    /**
     * Espera hasta que haya segmentos nuevos (long-poll) y los devuelve empaquetados
     * en binario: [index uint32 BE][length uint32 BE][data...] por cada segmento.
     */
    public function packChunksAfter(int $after, int $waitMs = 3000): string
    {
        $deadline = microtime(true) + ($waitMs / 1000);

        do {
            $binary = $this->buildPackAfter($after);
            if ($binary !== '' || ! $this->isActive()) {
                return $binary;
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        return '';
    }

    private function buildPackAfter(int $after): string
    {
        $latest = (int) Cache::get('live:index', -1);

        if ($latest < 0) {
            return '';
        }

        $from = $after < 0 ? 0 : $after + 1;
        $to = min($latest, $from + 5);
        $pack = '';

        for ($i = $from; $i <= $to; $i++) {
            $data = $this->readChunkBinaryForPack($i);
            if ($data !== null) {
                $pack .= $this->packChunk($i, $data);
            }
        }

        return $pack;
    }

    private function readChunkBinaryForPack(int $index): ?string
    {
        $data = $this->readChunkBinary($index);

        if ($data === null) {
            return null;
        }

        // Chunk 0: init + primer cluster. Resto: solo cluster (evita audio corrupto en MSE).
        if ($index > 0) {
            return $this->extractWebmMedia($data);
        }

        if ($this->getInitSegment()) {
            return $this->extractWebmMedia($data);
        }

        return $data;
    }

    private function readChunkBinary(int $index): ?string
    {
        $path = $this->getChunkPath($index);

        if (! $path || ! is_readable($path)) {
            return null;
        }

        $data = file_get_contents($path);

        return $data === false || strlen($data) < 10 ? null : $data;
    }

    private function packChunk(int $index, string $data): string
    {
        return pack('NN', $index, strlen($data)) . $data;
    }

    private function getInitSegment(): ?string
    {
        $cached = Cache::get('live:init');
        if (is_string($cached) && strlen($cached) > 10) {
            return $cached;
        }

        foreach (['webm', 'ogg', 'm4a'] as $ext) {
            $path = "{$this->chunkPath}/init.{$ext}";
            if (is_readable($path)) {
                $data = file_get_contents($path);

                return $data !== false && strlen($data) > 10 ? $data : null;
            }
        }

        $chunk0 = $this->readChunkBinary(0);

        return $chunk0 ? $this->extractWebmInit($chunk0) : null;
    }

    /** Separa la cabecera WebM del primer bloque de audio (Cluster). */
    private function extractWebmInit(string $data): string
    {
        $pos = strpos($data, self::WEBM_CLUSTER);

        if ($pos !== false && $pos > 0) {
            return substr($data, 0, $pos);
        }

        return $data;
    }

    /** Devuelve solo los clusters WebM (sin cabecera init repetida). */
    private function extractWebmMedia(string $data): string
    {
        $pos = strpos($data, self::WEBM_CLUSTER);

        if ($pos !== false) {
            return substr($data, $pos);
        }

        return $data;
    }

    private function appendToStream(int $index): void
    {
        $data = $this->readChunkBinaryForPack($index);

        if ($data === null) {
            return;
        }

        File::ensureDirectoryExists(dirname($this->streamPath));

        if ($index === 0 && file_exists($this->streamPath)) {
            @unlink($this->streamPath);
        }

        file_put_contents($this->streamPath, $data, FILE_APPEND | LOCK_EX);
        clearstatcache(true, $this->streamPath);
        Cache::put('live:stream_bytes', filesize($this->streamPath), now()->addHours(6));
    }

    private function pruneOldChunks(int $currentIndex): void
    {
        $threshold = $currentIndex - self::MAX_CHUNKS;
        if ($threshold < 0) {
            return;
        }

        $meta = Cache::get('live:chunk_meta', []);

        for ($i = 0; $i <= $threshold; $i++) {
            if ($i === 0) {
                continue;
            }
            $path = $this->getChunkPath($i);
            if ($path) {
                @unlink($path);
            }
            unset($meta[$i]);
        }

        Cache::put('live:chunk_meta', $meta, now()->addMinutes(15));
    }

    private function clearChunks(): void
    {
        if (File::isDirectory($this->chunkPath)) {
            File::cleanDirectory($this->chunkPath);
        }

        Cache::forget('live:chunk_meta');
        Cache::forget('live:init');

        if (file_exists($this->streamPath)) {
            @unlink($this->streamPath);
        }
    }
}
