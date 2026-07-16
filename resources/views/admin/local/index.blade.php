@extends('layouts.admin')

@section('title', 'Carpeta local')

@section('content')
<div class="mb-8">
    <h2 class="text-2xl font-bold">Carpeta de música local</h2>
    <p class="text-slate-400">Suelta tus canciones en esta carpeta del disco y escanéalas para importarlas.</p>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h3 class="font-semibold mb-2">Ruta de la carpeta</h3>
            <div class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 font-mono text-sm text-violet-300 break-all">
                {{ $folderPath }}
            </div>
            <p class="text-slate-500 text-xs mt-2">
                Copia esta ruta en el Explorador de Windows para abrir la carpeta y soltar tus archivos.
                Puedes cambiarla con <code class="text-violet-300">LOCAL_MUSIC_PATH</code> en el archivo <code>.env</code>.
            </p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold">Archivos en la carpeta ({{ count($files) }})</h3>
                <span class="text-sm text-slate-400">{{ $importedCount }} importadas</span>
            </div>

            @if(count($files))
                <div class="max-h-80 overflow-y-auto space-y-1 text-sm">
                    @foreach($files as $file)
                        <div class="flex items-center gap-2 px-3 py-2 bg-slate-800/50 rounded-lg">
                            <span>🎵</span>
                            <span class="text-slate-300 break-all">{{ $file }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-10 text-slate-500">
                    <div class="text-4xl mb-3">📂</div>
                    La carpeta está vacía. Copia archivos de audio (MP3, WAV, OGG, M4A, FLAC) y vuelve a cargar esta página.
                </div>
            @endif
        </div>

        @if(session('scan_errors') && count(session('scan_errors')))
            <div class="bg-red-900/30 border border-red-800 rounded-xl p-6">
                <h3 class="font-semibold text-red-300 mb-3">Archivos con error</h3>
                <ul class="text-sm text-red-300/80 space-y-1 list-disc list-inside">
                    @foreach(session('scan_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="space-y-6">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
            <h3 class="font-semibold">Escanear e importar</h3>

            <form action="{{ route('admin.local.scan') }}" method="POST" class="space-y-4">
                @csrf

                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="add_to_playlist" value="1" checked class="mt-1 rounded bg-slate-800 border-slate-700">
                    <div>
                        <span class="text-sm font-medium">Agregar a playlist activa</span>
                        <p class="text-xs text-slate-500 mt-1">
                            @if($defaultPlaylist)
                                {{ $defaultPlaylist->name }}
                            @else
                                No hay playlist por defecto
                            @endif
                        </p>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="fetch_online" value="1" class="mt-1 rounded bg-slate-800 border-slate-700">
                    <div>
                        <span class="text-sm font-medium">Buscar info en internet</span>
                        <p class="text-xs text-slate-500 mt-1">Más lento. Completa artista/álbum vía MusicBrainz.</p>
                    </div>
                </label>

                <button type="submit" class="w-full bg-violet-600 hover:bg-violet-500 px-4 py-2.5 rounded-lg text-sm font-semibold">
                    🔄 Escanear carpeta
                </button>
            </form>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h3 class="font-semibold mb-3">Mantenimiento</h3>
            <form action="{{ route('admin.local.clean') }}" method="POST" onsubmit="return confirm('¿Quitar de la biblioteca las canciones cuyo archivo ya no está en la carpeta?')">
                @csrf
                <button type="submit" class="w-full bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg text-sm">
                    Quitar canciones sin archivo
                </button>
            </form>
            <p class="text-xs text-slate-500 mt-2">
                Útil si borraste archivos de la carpeta y quieres limpiarlos de la emisora.
            </p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h3 class="font-semibold mb-3">Cómo funciona</h3>
            <ol class="text-sm text-slate-400 space-y-2 list-decimal list-inside">
                <li>Copia tus MP3 a la carpeta</li>
                <li>Pulsa "Escanear carpeta"</li>
                <li>Se importan sin copiarse</li>
                <li>Se reproducen desde el disco</li>
            </ol>
        </div>
    </div>
</div>
@endsection
