<?php

namespace App\Services;

use getID3;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SongMetadataService
{
    public function extract(UploadedFile $file, bool $fetchOnline = true): array
    {
        $fromTags = $this->extractFromFile($file);
        $fromFilename = $this->parseFilename($file->getClientOriginalName());

        $title = $fromTags['title'] ?: $fromFilename['title'];
        $artist = $fromTags['artist'] ?: $fromFilename['artist'];
        $album = $fromTags['album'] ?: $fromFilename['album'];
        $duration = $fromTags['duration'];

        if ($fetchOnline && $title) {
            $online = $this->lookupOnline($title, $artist);
            $title = $online['title'] ?: $title;
            $artist = $online['artist'] ?: $artist;
            $album = $online['album'] ?: $album;
        }

        if (! $title) {
            $title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        }

        return [
            'title' => Str::limit(trim($title), 255, ''),
            'artist' => $artist ? Str::limit(trim($artist), 255, '') : null,
            'album' => $album ? Str::limit(trim($album), 255, '') : null,
            'duration' => $duration,
        ];
    }

    public function extractFromFile(UploadedFile $file): array
    {
        return $this->analyzePath($file->getPathname());
    }

    public function extractFromPath(string $absolutePath, bool $fetchOnline = true): array
    {
        $fromTags = $this->analyzePath($absolutePath);
        $fromFilename = $this->parseFilename(basename($absolutePath));

        $title = $fromTags['title'] ?: $fromFilename['title'];
        $artist = $fromTags['artist'] ?: $fromFilename['artist'];
        $album = $fromTags['album'] ?: $fromFilename['album'];
        $duration = $fromTags['duration'];

        if ($fetchOnline && $title) {
            $online = $this->lookupOnline($title, $artist);
            $title = $online['title'] ?: $title;
            $artist = $online['artist'] ?: $artist;
            $album = $online['album'] ?: $album;
        }

        if (! $title) {
            $title = pathinfo($absolutePath, PATHINFO_FILENAME);
        }

        return [
            'title' => Str::limit(trim($title), 255, ''),
            'artist' => $artist ? Str::limit(trim($artist), 255, '') : null,
            'album' => $album ? Str::limit(trim($album), 255, '') : null,
            'duration' => $duration,
        ];
    }

    private function analyzePath(string $path): array
    {
        try {
            $getID3 = new getID3;
            $info = $getID3->analyze($path);
            $tags = $info['tags']['id3v2'] ?? $info['tags']['id3v1'] ?? $info['tags']['quicktime'] ?? [];

            return [
                'title' => $this->firstTag($tags, ['title', 'track_title', 'songname']),
                'artist' => $this->firstTag($tags, ['artist', 'band', 'albumartist']),
                'album' => $this->firstTag($tags, ['album']),
                'duration' => isset($info['playtime_seconds']) ? (int) round($info['playtime_seconds']) : 0,
            ];
        } catch (\Throwable) {
            return ['title' => null, 'artist' => null, 'album' => null, 'duration' => 0];
        }
    }

    public function parseFilename(string $filename): array
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/^\d+[\.\-\s]*/', '', $name);

        $patterns = [
            '/^(?<artist>.+?)\s*[-–—]\s*(?<title>.+)$/u',
            '/^(?<artist>.+?)\s*_\s*(?<title>.+)$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name, $matches)) {
                return [
                    'title' => trim($matches['title']),
                    'artist' => trim($matches['artist']),
                    'album' => null,
                ];
            }
        }

        return ['title' => trim($name), 'artist' => null, 'album' => null];
    }

    public function lookupOnline(string $title, ?string $artist = null): array
    {
        try {
            $query = $artist
                ? sprintf('recording:"%s" AND artist:"%s"', $this->escapeQuery($title), $this->escapeQuery($artist))
                : sprintf('recording:"%s"', $this->escapeQuery($title));

            $response = Http::withHeaders([
                'User-Agent' => 'EmisoraOnline/1.0 (https://github.com/emisora-online)',
                'Accept' => 'application/json',
            ])->timeout(8)->get('https://musicbrainz.org/ws/2/recording', [
                'query' => $query,
                'fmt' => 'json',
                'limit' => 1,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $recording = $response->json('recordings.0');

            if (! $recording) {
                if ($artist) {
                    return $this->lookupOnline($title, null);
                }

                return [];
            }

            $album = collect($recording['releases'] ?? [])
                ->pluck('title')
                ->filter()
                ->first();

            $artistName = collect($recording['artist-credit'] ?? [])
                ->pluck('name')
                ->filter()
                ->implode(', ');

            return [
                'title' => $recording['title'] ?? null,
                'artist' => $artistName ?: null,
                'album' => $album,
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    private function firstTag(array $tags, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! empty($tags[$key][0])) {
                return is_array($tags[$key][0])
                    ? ($tags[$key][0]['data'] ?? null)
                    : (string) $tags[$key][0];
            }
        }

        return null;
    }

    private function escapeQuery(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }
}
