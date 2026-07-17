<?php

namespace App\Services;

use App\Models\StationSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LiveStreamService
{
    private const MAX_CHUNKS = 240;

    /** Máximo de segmentos recientes al conectar un oyente nuevo (evita descargar todo el historial). */
    private const JOIN_CATCHUP_CHUNKS = 4;

    private const WEBM_CLUSTER = "\x1F\x43\xB6\x75";

    private string $chunkPath;

    private string $streamPath;

    private string $statePath;

    public function __construct()
    {
        $this->chunkPath = storage_path('app/live/chunks');
        $this->streamPath = storage_path('app/live/stream.webm');
        $this->statePath = storage_path('app/live/state.json');
    }

    /** Intervalo mínimo entre chunks aceptados (evita 2–3 uploaders duplicados). */
    private const MIN_CHUNK_INTERVAL_MS = 700;

    public function start(?string $hostName = null): string
    {
        $station = StationSetting::current();
        $host = $hostName ?: 'Locutor en vivo';

        // Evita que una segunda instancia del broadcaster borre chunks en curso.
        if ($this->isActive()) {
            $state = $this->readLiveState();
            $sessionId = (string) ($state['session_id'] ?? '');
            if ($sessionId === '') {
                $sessionId = Str::random(32);
                $this->writeLiveState(['host' => $host, 'session_id' => $sessionId]);
            } else {
                $this->writeLiveState(['host' => $host]);
            }
            $station->update(['live_host_name' => $host]);

            return $sessionId;
        }

        $this->clearChunks();
        File::ensureDirectoryExists($this->chunkPath);

        $startedAt = now()->toIso8601String();
        $sessionId = Str::random(32);

        $this->writeLiveState([
            'active' => true,
            'index' => -1,
            'host' => $host,
            'started_at' => $startedAt,
            'last_chunk_at' => null,
            'stream_bytes' => 0,
            'session_id' => $sessionId,
        ]);

        $station->update([
            'is_live' => true,
            'live_host_name' => $hostName,
            'live_started_at' => now(),
        ]);

        return $sessionId;
    }

    public function stop(): void
    {
        $this->clearChunks();

        if (file_exists($this->statePath)) {
            @unlink($this->statePath);
        }

        StationSetting::current()->update([
            'is_live' => false,
            'live_host_name' => null,
            'live_started_at' => null,
        ]);
    }

    public function addChunk(string $binaryData, string $mime, ?string $sessionId = null): ?int
    {
        File::ensureDirectoryExists($this->chunkPath);

        $state = $this->readLiveState();
        $expectedSession = (string) ($state['session_id'] ?? '');

        if ($expectedSession !== '') {
            if ($sessionId === null || $sessionId === '' || ! hash_equals($expectedSession, $sessionId)) {
                return null;
            }
        }

        $currentIndex = (int) ($state['index'] ?? -1);
        $lastAt = $state['last_chunk_at'] ?? null;

        if ($lastAt && $currentIndex >= 0) {
            $elapsed = (int) ((microtime(true) - strtotime($lastAt)) * 1000);
            if ($elapsed < self::MIN_CHUNK_INTERVAL_MS) {
                return $currentIndex;
            }
        }

        $index = $currentIndex + 1;
        $extension = str_contains($mime, 'ogg') ? 'ogg' : (str_contains($mime, 'mp4') ? 'm4a' : 'webm');

        file_put_contents("{$this->chunkPath}/{$index}.{$extension}", $binaryData);

        if ($index === 0) {
            $init = $this->extractWebmInit($binaryData);
            file_put_contents("{$this->chunkPath}/init.{$extension}", $init);
        }

        $this->writeLiveState([
            'active' => true,
            'index' => $index,
            'last_chunk_at' => now()->toIso8601String(),
        ]);

        if ($index > 0 && $index % 20 === 0) {
            $this->pruneOldChunks($index);
        }

        return $index;
    }

    /** @deprecated Ya no se usa en el hot path; pack lee chunks individuales. */
    public function finalizeChunk(int $index): void
    {
    }

    public function getChunkPath(int $index): ?string
    {
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
        $path = $this->getChunkPath($index);

        if (! $path) {
            return 'audio/webm';
        }

        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            default => 'audio/webm',
        };
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

        return (int) ($this->readLiveState()['stream_bytes'] ?? 0);
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
        return (bool) ($this->readLiveState()['active'] ?? false);
    }

    public function getLatestIndex(): int
    {
        return (int) ($this->readLiveState()['index'] ?? -1);
    }

    public function getStatus(): array
    {
        $state = $this->readLiveState();

        return [
            'is_live' => (bool) ($state['active'] ?? false),
            'host_name' => $state['host'] ?? 'Locutor en vivo',
            'started_at' => $state['started_at'] ?? null,
            'latest_index' => (int) ($state['index'] ?? -1),
        ];
    }

    public function getChunksAfter(int $after, int $limit = 0): array
    {
        $latest = $this->getLatestIndex();
        $after = $this->normalizeAfterIndex($after, $latest);
        $chunks = [];

        $start = $after + 1;

        if ($start <= 0 && $this->getChunkPath(0)) {
            $chunks[] = [
                'index' => 0,
                'url' => url('/api/live/audio/0'),
                'mime' => $this->getChunkMime(0),
            ];
            $start = 1;
        }

        for ($i = $start; $i <= $latest; $i++) {
            if ($this->getChunkPath($i)) {
                $chunks[] = [
                    'index' => $i,
                    'url' => url("/api/live/audio/{$i}"),
                    'mime' => $this->getChunkMime($i),
                ];
            }
        }

        if ($limit > 0 && count($chunks) > $limit) {
            $chunks = array_slice($chunks, -$limit);
        }

        return $chunks;
    }

    /**
     * Devuelve segmentos empaquetados en binario: [index uint32 BE][length uint32 BE][data...]
     */
    public function packChunksAfter(int $after): string
    {
        if (! $this->isActive()) {
            return '';
        }

        return $this->buildPackAfter($after);
    }

    private function buildPackAfter(int $after): string
    {
        $latest = $this->getLatestIndex();

        if ($latest < 0) {
            return '';
        }

        $after = $this->normalizeAfterIndex($after, $latest);

        $from = $after < 0 ? 0 : $after + 1;
        $to = min($latest, $from + 3);
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

    /** Si el oyente pide chunks viejos (p. ej. tras reinicio del broadcaster), saltar al borde en vivo. */
    private function normalizeAfterIndex(int $after, int $latest): int
    {
        if ($latest < 0) {
            return -1;
        }

        if ($after > $latest) {
            return max(-1, $latest - self::JOIN_CATCHUP_CHUNKS);
        }

        if ($after >= 0 && ! $this->getChunkPath($after + 1) && $latest > $after) {
            for ($i = $after + 1; $i <= $latest; $i++) {
                if ($this->getChunkPath($i)) {
                    return $i - 1;
                }
            }

            return $latest;
        }

        return $after;
    }

    private function packChunk(int $index, string $data): string
    {
        return pack('NN', $index, strlen($data)) . $data;
    }

    private function getInitSegment(): ?string
    {
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
        $this->writeLiveState(['stream_bytes' => (int) filesize($this->streamPath)]);
    }

    private function pruneOldChunks(int $currentIndex): void
    {
        $threshold = $currentIndex - self::MAX_CHUNKS;
        if ($threshold < 0) {
            return;
        }

        for ($i = 0; $i <= $threshold; $i++) {
            if ($i === 0) {
                continue;
            }

            foreach (['webm', 'ogg', 'm4a'] as $ext) {
                @unlink("{$this->chunkPath}/{$i}.{$ext}");
            }
        }
    }

    private function clearChunks(): void
    {
        if (File::isDirectory($this->chunkPath)) {
            File::cleanDirectory($this->chunkPath);
        }

        if (file_exists($this->streamPath)) {
            @unlink($this->streamPath);
        }
    }

    private function readLiveState(): array
    {
        if (! is_readable($this->statePath)) {
            return [
                'active' => false,
                'index' => -1,
                'host' => null,
                'started_at' => null,
                'last_chunk_at' => null,
                'stream_bytes' => 0,
            ];
        }

        $data = json_decode((string) file_get_contents($this->statePath), true);

        if (! is_array($data)) {
            return ['active' => false, 'index' => -1];
        }

        return array_merge([
            'active' => false,
            'index' => -1,
            'host' => null,
            'started_at' => null,
            'last_chunk_at' => null,
            'stream_bytes' => 0,
            'session_id' => null,
        ], $data);
    }

    private function writeLiveState(array $patch): void
    {
        File::ensureDirectoryExists(dirname($this->statePath));
        $state = array_merge($this->readLiveState(), $patch);
        file_put_contents($this->statePath, json_encode($state), LOCK_EX);
    }
}
