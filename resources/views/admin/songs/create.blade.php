@extends('layouts.admin')

@section('title', 'Subir canciones')

@section('content')
<div class="mb-8">
    <h2 class="text-2xl font-bold">Subir canciones</h2>
    <p class="text-slate-400">Arrastra carpetas o múltiples archivos. Máx. <strong>40 MB</strong> por canción (límite de XAMPP).</p>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <!-- Zona de arrastre -->
        <div id="drop-zone"
            class="border-2 border-dashed border-violet-500/40 rounded-2xl p-12 text-center bg-slate-900/50 hover:border-violet-500/70 hover:bg-violet-900/10 transition cursor-pointer">
            <div class="text-5xl mb-4">🎵</div>
            <p class="text-lg font-medium">Arrastra aquí tus canciones o carpetas</p>
            <p class="text-slate-400 text-sm mt-2">MP3, WAV, OGG, M4A, FLAC — máx. 50MB por archivo</p>
            <div class="flex justify-center gap-3 mt-6">
                <label class="bg-violet-600 hover:bg-violet-500 px-5 py-2.5 rounded-lg text-sm font-medium cursor-pointer">
                    Elegir archivos
                    <input type="file" id="file-input" multiple accept="audio/*,.mp3,.wav,.ogg,.m4a,.flac,.aac" class="hidden">
                </label>
                <label class="bg-slate-700 hover:bg-slate-600 px-5 py-2.5 rounded-lg text-sm font-medium cursor-pointer">
                    Elegir carpeta
                    <input type="file" id="folder-input" webkitdirectory directory multiple class="hidden">
                </label>
            </div>
        </div>

        <!-- Progreso -->
        <div id="upload-panel" class="hidden bg-slate-900 border border-slate-800 rounded-xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold">Subiendo canciones...</h3>
                <span id="upload-counter" class="text-sm text-slate-400">0 / 0</span>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-2 mb-4">
                <div id="upload-progress" class="bg-violet-500 h-2 rounded-full transition-all" style="width: 0%"></div>
            </div>
            <div id="upload-log" class="max-h-64 overflow-y-auto space-y-2 text-sm"></div>
        </div>

        <!-- Resultados -->
        <div id="results-panel" class="hidden bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h3 class="font-semibold mb-4 text-green-400">✓ Subida completada</h3>
            <div id="results-list" class="space-y-2 text-sm"></div>
            <a href="{{ route('admin.songs.index') }}" class="inline-block mt-4 bg-violet-600 hover:bg-violet-500 px-4 py-2 rounded-lg text-sm">
                Ver biblioteca
            </a>
        </div>
    </div>

    <!-- Opciones -->
    <div class="space-y-6">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
            <h3 class="font-semibold">Opciones automáticas</h3>

            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" id="opt-online" checked class="mt-1 rounded bg-slate-800 border-slate-700">
                <div>
                    <span class="text-sm font-medium">Buscar info en internet</span>
                    <p class="text-xs text-slate-500 mt-1">Usa MusicBrainz para completar artista, álbum y título</p>
                </div>
            </label>

            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" id="opt-playlist" checked class="mt-1 rounded bg-slate-800 border-slate-700">
                <div>
                    <span class="text-sm font-medium">Agregar a playlist activa</span>
                    <p class="text-xs text-slate-500 mt-1">
                        @if($defaultPlaylist)
                            Se agregarán a: <strong class="text-violet-300">{{ $defaultPlaylist->name }}</strong>
                        @else
                            Crea una playlist primero
                        @endif
                    </p>
                </div>
            </label>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h3 class="font-semibold mb-3">¿Cómo detectamos la info?</h3>
            <ol class="text-sm text-slate-400 space-y-2 list-decimal list-inside">
                <li>Etiquetas ID3 del archivo</li>
                <li>Nombre del archivo (Artista - Título)</li>
                <li>Búsqueda en MusicBrainz</li>
            </ol>
        </div>

        <a href="{{ route('admin.songs.index') }}" class="block text-center text-slate-400 hover:text-white text-sm">
            ← Volver a canciones
        </a>
    </div>
</div>

<script>
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const folderInput = document.getElementById('folder-input');
const uploadPanel = document.getElementById('upload-panel');
const resultsPanel = document.getElementById('results-panel');
const uploadLog = document.getElementById('upload-log');
const resultsList = document.getElementById('results-list');
const uploadProgress = document.getElementById('upload-progress');
const uploadCounter = document.getElementById('upload-counter');

const AUDIO_EXTENSIONS = ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac', 'mpeg'];

function isAudioFile(file) {
    if (file.type.startsWith('audio/')) return true;
    const ext = file.name.split('.').pop()?.toLowerCase();
    return AUDIO_EXTENSIONS.includes(ext);
}

function handleFiles(fileList) {
    const files = Array.from(fileList).filter(isAudioFile);
    if (!files.length) {
        alert('No se encontraron archivos de audio válidos.');
        return;
    }
    uploadFiles(files);
}

['dragenter', 'dragover'].forEach(evt => {
    dropZone.addEventListener(evt, e => {
        e.preventDefault();
        dropZone.classList.add('border-violet-500', 'bg-violet-900/20');
    });
});

['dragleave', 'drop'].forEach(evt => {
    dropZone.addEventListener(evt, e => {
        e.preventDefault();
        dropZone.classList.remove('border-violet-500', 'bg-violet-900/20');
    });
});

dropZone.addEventListener('drop', e => handleFiles(e.dataTransfer.files));
fileInput.addEventListener('change', e => handleFiles(e.target.files));
folderInput.addEventListener('change', e => handleFiles(e.target.files));

function parseUploadError(data, file) {
    if (data.error) return data.error;
    if (data.errors?.audio) return data.errors.audio[0];
    if (data.message) return data.message;
    if (file.size > 40 * 1024 * 1024) return 'Archivo muy grande (máx. 40 MB)';
    return 'Error desconocido';
}

async function uploadFiles(files) {
    uploadPanel.classList.remove('hidden');
    resultsPanel.classList.add('hidden');
    uploadLog.innerHTML = '';
    resultsList.innerHTML = '';

    const total = files.length;
    let done = 0;
    let success = 0;

    uploadCounter.textContent = `0 / ${total}`;

    for (const file of files) {
        const entry = document.createElement('div');
        entry.className = 'flex items-center gap-2 text-slate-400';
        entry.innerHTML = `<span class="animate-pulse">⏳</span> <span>${file.name}</span>`;
        uploadLog.prepend(entry);

        if (file.size > 40 * 1024 * 1024) {
            done++;
            entry.className = 'flex items-center gap-2 text-red-400';
            entry.innerHTML = `<span>✗</span> <span>${file.name}: Muy grande (${Math.round(file.size/1024/1024)} MB, máx. 40 MB)</span>`;
            uploadCounter.textContent = `${done} / ${total}`;
            uploadProgress.style.width = `${(done / total) * 100}%`;
            continue;
        }

        const formData = new FormData();
        formData.append('audio', file);
        formData.append('fetch_online', document.getElementById('opt-online').checked ? '1' : '0');
        formData.append('add_to_playlist', document.getElementById('opt-playlist').checked ? '1' : '0');

        try {
            const res = await fetch('{{ route('admin.songs.upload') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: formData,
            });

            const data = await res.json().catch(() => ({}));
            done++;

            if (res.ok && data.success) {
                success++;
                entry.className = 'flex items-center gap-2 text-green-400';
                entry.innerHTML = `<span>✓</span> <span><strong>${data.song.title}</strong> — ${data.song.artist || 'Sin artista'}${data.song.album ? ' · ' + data.song.album : ''}</span>`;

                const result = document.createElement('div');
                result.className = 'bg-slate-800 rounded-lg px-3 py-2';
                result.innerHTML = `<strong>${data.song.title}</strong> — ${data.song.artist || '?'} · ${data.song.album || 'Sin álbum'} · ${data.song.duration}`;
                resultsList.appendChild(result);
            } else {
                entry.className = 'flex items-center gap-2 text-red-400';
                entry.innerHTML = `<span>✗</span> <span>${file.name}: ${parseUploadError(data, file)}</span>`;
            }
        } catch (err) {
            done++;
            entry.className = 'flex items-center gap-2 text-red-400';
            entry.innerHTML = `<span>✗</span> <span>${file.name}: Error de conexión</span>`;
        }

        uploadCounter.textContent = `${done} / ${total}`;
        uploadProgress.style.width = `${(done / total) * 100}%`;

        // Respetar rate limit de MusicBrainz (1 req/seg)
        if (document.getElementById('opt-online').checked && done < total) {
            await new Promise(r => setTimeout(r, 1100));
        }
    }

    if (success > 0) {
        resultsPanel.classList.remove('hidden');
        resultsPanel.querySelector('h3').textContent = `✓ ${success} de ${total} canciones subidas`;
    }
}
</script>
@endsection
