<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\Song;
use Illuminate\Support\Facades\File;

class LocalMusicService
{
    public function __construct(private SongMetadataService $metadata) {}

    public function folderPath(): string
    {
        return rtrim((string) config('emisora.music_folder'), '/\\');
    }

    public function ensureFolder(): void
    {
        File::ensureDirectoryExists($this->folderPath());
    }

    public function folderExists(): bool
    {
        return File::isDirectory($this->folderPath());
    }

    /**
     * Lista los archivos de audio de la carpeta (incluyendo subcarpetas)
     * como rutas relativas a la carpeta base.
     *
     * @return string[]
     */
    public function listAudioFiles(): array
    {
        if (! $this->folderExists()) {
            return [];
        }

        $extensions = collect(config('emisora.audio_extensions', []))
            ->map(fn ($e) => strtolower($e))
            ->all();

        $base = $this->folderPath();
        $files = [];

        foreach (File::allFiles($base) as $file) {
            if (in_array(strtolower($file->getExtension()), $extensions, true)) {
                $relative = ltrim(str_replace($base, '', $file->getPathname()), '/\\');
                $files[] = str_replace('\\', '/', $relative);
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Escanea la carpeta e importa las canciones nuevas.
     *
     * @return array{imported:int, skipped:int, failed:int, errors:array<string>}
     */
    public function scan(bool $fetchOnline = false, bool $addToPlaylist = true): array
    {
        $this->ensureFolder();

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $existing = Song::where('source', 'folder')->pluck('file_path')->all();
        $existing = array_flip($existing);

        $playlist = $addToPlaylist ? Playlist::getDefault() : null;

        foreach ($this->listAudioFiles() as $relative) {
            if (isset($existing[$relative])) {
                $skipped++;
                continue;
            }

            $absolute = $this->folderPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            try {
                $meta = $this->metadata->extractFromPath($absolute, $fetchOnline);

                Song::create([
                    'title' => $meta['title'],
                    'artist' => $meta['artist'],
                    'album' => $meta['album'],
                    'file_path' => $relative,
                    'source' => 'folder',
                    'is_active' => true,
                    'file_size' => is_file($absolute) ? filesize($absolute) : 0,
                    'duration' => $meta['duration'] ?? 0,
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = basename($relative) . ': ' . $e->getMessage();
            }
        }

        // Asegura que TODAS las canciones de la carpeta estén en la playlist,
        // incluso las que ya existían de escaneos anteriores.
        $attached = $playlist ? $this->syncToPlaylist($playlist) : 0;

        return compact('imported', 'skipped', 'failed', 'errors', 'attached');
    }

    /**
     * Vincula a la playlist todas las canciones de la carpeta que aún no estén en ella.
     */
    public function syncToPlaylist(Playlist $playlist): int
    {
        $inPlaylist = $playlist->songs()->pluck('song_id')->flip();
        $position = $playlist->songs()->count();
        $attached = 0;

        $folderSongs = Song::where('source', 'folder')->orderBy('id')->get();

        foreach ($folderSongs as $song) {
            if ($inPlaylist->has($song->id)) {
                continue;
            }

            $playlist->songs()->attach($song->id, ['position' => $position]);
            $position++;
            $attached++;
        }

        return $attached;
    }

    /**
     * Elimina de la biblioteca las canciones locales cuyo archivo ya no existe.
     */
    public function removeMissing(): int
    {
        $removed = 0;

        foreach (Song::where('source', 'folder')->get() as $song) {
            $absolute = $this->folderPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $song->file_path);
            if (! is_file($absolute)) {
                $song->delete();
                $removed++;
            }
        }

        return $removed;
    }

    public function localSongsCount(): int
    {
        return Song::where('source', 'folder')->count();
    }
}
