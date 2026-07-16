@extends('layouts.admin')

@section('title', 'Estudio DJ en vivo')

@section('content')
<div class="mb-8">
    <h2 class="text-2xl font-bold">Estudio DJ en vivo</h2>
    <p class="text-slate-400">Mezcla micrófono, audio de tu PC (Zara Radio, etc.) y música local mientras transmites</p>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        {{-- Estado transmisión --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8 text-center">
            <div id="status-idle" class="{{ $status['is_live'] ? 'hidden' : '' }}">
                <div class="text-6xl mb-4">🎛️</div>
                <p class="text-slate-400 mb-6">Configura el mezclador y pulsa "Iniciar transmisión"</p>
            </div>

            <div id="status-live" class="{{ $status['is_live'] ? '' : 'hidden' }}">
                <div class="inline-flex items-center gap-2 bg-red-600/80 px-4 py-2 rounded-full text-sm font-medium mb-6 animate-pulse">
                    <span class="w-2 h-2 bg-white rounded-full"></span> TRANSMITIENDO EN VIVO
                </div>
                <div class="flex justify-center items-end gap-1 h-16 mb-4" id="mic-visualizer">
                    @for ($i = 0; $i < 20; $i++)
                        <div class="w-1.5 bg-violet-500 rounded-full transition-all duration-75" style="height: 8px"></div>
                    @endfor
                </div>
                <p class="text-slate-400 text-sm">Los oyentes escuchan la mezcla que envías desde aquí</p>
            </div>

            <div class="flex justify-center gap-4 mt-8">
                <button id="btn-start" class="{{ $status['is_live'] ? 'hidden' : '' }} bg-red-600 hover:bg-red-500 px-8 py-3 rounded-xl font-semibold text-lg">
                    🔴 Iniciar transmisión
                </button>
                <button id="btn-stop" class="{{ $status['is_live'] ? '' : 'hidden' }} bg-slate-700 hover:bg-slate-600 px-8 py-3 rounded-xl font-semibold text-lg">
                    ⏹ Detener transmisión
                </button>
            </div>
        </div>

        {{-- Mezclador --}}
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-5">
            <h3 class="font-semibold text-lg">Mezclador local</h3>

            {{-- Micrófono --}}
            <div class="bg-slate-800/60 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🎙️</span>
                        <span class="font-medium">Micrófono</span>
                    </div>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" id="mic-enabled" checked class="rounded bg-slate-700 border-slate-600">
                        Activo
                    </label>
                </div>
                <input type="range" id="mic-vol" min="0" max="100" value="100" class="w-full accent-violet-500">
                <p class="text-xs text-slate-500 mt-1">Tu voz en la transmisión</p>
            </div>

            {{-- Fuente de música --}}
            <div class="bg-slate-800/60 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🎵</span>
                        <span class="font-medium">Música (fuente limpia)</span>
                    </div>
                    <span id="music-status" class="text-xs px-2 py-1 rounded bg-slate-700 text-slate-400">Deck local</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-3 text-sm">
                    <label class="flex items-center gap-2 bg-slate-900/60 rounded-lg px-3 py-2 cursor-pointer border border-violet-500/50">
                        <input type="radio" name="music-source" value="deck" checked class="accent-violet-500">
                        <span>Deck local <span class="text-emerald-400 text-xs">✓ recomendado</span></span>
                    </label>
                    <label class="flex items-center gap-2 bg-slate-900/60 rounded-lg px-3 py-2 cursor-pointer">
                        <input type="radio" name="music-source" value="cable" class="accent-violet-500">
                        <span>VB-Cable / línea</span>
                    </label>
                    <label class="flex items-center gap-2 bg-slate-900/60 rounded-lg px-3 py-2 cursor-pointer">
                        <input type="radio" name="music-source" value="capture" class="accent-violet-500">
                        <span>Captura pantalla</span>
                    </label>
                </div>

                <div id="cable-panel" class="hidden mb-3">
                    <select id="cable-device" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm mb-2">
                        <option value="">— Selecciona entrada de audio —</option>
                    </select>
                    <button id="btn-connect-cable" class="w-full bg-emerald-700 hover:bg-emerald-600 px-4 py-2 rounded-lg text-sm">
                        🔌 Conectar VB-Cable / línea
                    </button>
                    <p class="text-xs text-slate-500 mt-2">Configura Zara Radio → salida VB-Cable. Audio digital sin captura de pantalla.</p>
                </div>

                <div id="capture-panel" class="hidden mb-3">
                    <button id="btn-capture-pc" class="w-full bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg text-sm">
                        🔗 Capturar ventana (calidad baja)
                    </button>
                    <p class="text-xs text-amber-500/80 mt-2">Solo si no tienes VB-Cable. Puede sonar con ruido o cortes.</p>
                </div>

                <input type="range" id="music-vol" min="0" max="100" value="80" class="w-full accent-emerald-500">
                <p class="text-xs text-slate-500 mt-1">Volumen de la música en la mezcla</p>
            </div>

            {{-- Deck local --}}
            <div class="bg-slate-800/60 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xl">📀</span>
                    <span class="font-medium">Deck local (carpeta / biblioteca)</span>
                </div>

                <div class="flex gap-2 mb-3">
                    <input type="search" id="deck-search" placeholder="Buscar canción..."
                        class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                    <select id="deck-select" class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Selecciona canción —</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 mb-3">
                    <button id="btn-deck-play" class="bg-violet-600 hover:bg-violet-500 px-4 py-2 rounded-lg text-sm" disabled>▶ Play</button>
                    <button id="btn-deck-pause" class="bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg text-sm" disabled>⏸ Pausa</button>
                    <button id="btn-deck-stop" class="bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg text-sm" disabled>⏹ Stop</button>
                    <button id="btn-deck-next" class="bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg text-sm" disabled>⏭ Siguiente</button>
                </div>

                <input type="range" id="deck-vol" min="0" max="100" value="80" class="w-full accent-violet-500">
                <p id="deck-now" class="text-xs text-slate-400 mt-2 truncate">Sin canción en el deck</p>
                <p class="text-xs text-slate-500 mt-1">Reproduce desde tu carpeta local o canciones subidas — no usa la playlist del oyente</p>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h3 class="font-semibold mb-4">Log de transmisión</h3>
            <div id="broadcast-log" class="text-sm text-slate-400 space-y-1 max-h-40 overflow-y-auto font-mono">
                <p>Esperando inicio...</p>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
            <h3 class="font-semibold">Configuración</h3>
            <div>
                <label class="block text-sm text-slate-400 mb-2">Nombre del locutor</label>
                <input type="text" id="host-name" value="{{ auth()->user()->name }}" maxlength="100"
                    class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2" {{ $status['is_live'] ? 'disabled' : '' }}>
            </div>
            <div class="text-sm text-slate-500 space-y-2">
                <p><strong class="text-slate-300">Segmentos enviados:</strong> <span id="chunk-count">0</span></p>
                <p><strong class="text-slate-300">Último índice:</strong> <span id="chunk-latest">—</span></p>
                <p><strong class="text-slate-300">Duración:</strong> <span id="duration">00:00</span></p>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h3 class="font-semibold mb-3">Mejor calidad de audio</h3>
            <ol class="text-sm text-slate-400 space-y-2 list-decimal list-inside">
                <li><strong class="text-emerald-300">Recomendado:</strong> usa el <strong class="text-slate-300">Deck local</strong> — reproduce MP3 directo, sin captura</li>
                <li><strong class="text-slate-300">Con Zara Radio:</strong> instala <a href="https://vb-audio.com/Cable/" target="_blank" class="text-violet-400 hover:underline">VB-Cable</a>, salida Zara → VB-Cable, elige "VB-Cable" aquí</li>
                <li>El audio pasa por filtro + compresor + limitador antes de codificar <strong class="text-slate-300">Opus 96kbps</strong></li>
                <li>Evita "Captura pantalla" — re-codifica el audio y suena mal</li>
            </ol>
        </div>

        <a href="{{ route('player') }}" target="_blank" class="block text-center bg-violet-600 hover:bg-violet-500 px-4 py-2 rounded-lg text-sm font-medium">
            🎧 Ver como oyente
        </a>
    </div>
</div>

<audio id="deck-player" class="hidden" preload="auto" crossorigin="anonymous"></audio>

<script>
const LIBRARY = @json($library);

const btnStart = document.getElementById('btn-start');
const btnStop = document.getElementById('btn-stop');
const statusIdle = document.getElementById('status-idle');
const statusLive = document.getElementById('status-live');
const broadcastLog = document.getElementById('broadcast-log');
const chunkCount = document.getElementById('chunk-count');
const chunkLatest = document.getElementById('chunk-latest');
const durationEl = document.getElementById('duration');
const hostNameInput = document.getElementById('host-name');

const micEnabled = document.getElementById('mic-enabled');
const micVol = document.getElementById('mic-vol');
const musicVol = document.getElementById('music-vol');
const deckVol = document.getElementById('deck-vol');
const btnCapturePc = document.getElementById('btn-capture-pc');
const musicStatus = document.getElementById('music-status');
const cablePanel = document.getElementById('cable-panel');
const capturePanel = document.getElementById('capture-panel');
const cableDevice = document.getElementById('cable-device');
const btnConnectCable = document.getElementById('btn-connect-cable');
const musicSourceRadios = document.querySelectorAll('input[name="music-source"]');

const deckSelect = document.getElementById('deck-select');
const deckSearch = document.getElementById('deck-search');
const btnDeckPlay = document.getElementById('btn-deck-play');
const btnDeckPause = document.getElementById('btn-deck-pause');
const btnDeckStop = document.getElementById('btn-deck-stop');
const btnDeckNext = document.getElementById('btn-deck-next');
const deckNow = document.getElementById('deck-now');
const deckPlayer = document.getElementById('deck-player');

let micStream = null;
let musicStream = null;
let mediaRecorder = null;
let mixerContext = null;
let mixerDestination = null;
let micGain = null;
let musicGain = null;
let deckGain = null;
let monitorGain = null;
let masterBus = null;
let analyser = null;
let animationId = null;
let startTime = null;
let durationInterval = null;
let chunksSent = 0;
let isBroadcasting = false;
let externalBroadcast = {{ $status['is_live'] ? 'true' : 'false' }};
let externalLiveLogged = false;
let deckIndex = -1;
let filteredLibrary = [...LIBRARY];
let musicConnected = false;
let musicSourceMode = 'deck';

const uploadQueue = [];
let uploadBusy = false;

function log(msg) {
    const p = document.createElement('p');
    p.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
    broadcastLog.prepend(p);
}

function getMimeType() {
    const types = [
        'audio/webm;codecs=opus',
        'audio/webm; codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
    ];
    return types.find(t => MediaRecorder.isTypeSupported(t)) || 'audio/webm';
}

function getRecordingSettings() {
    return {
        interval: 1000,
        bitrate: 96000,
        label: 'Opus limpio 1s',
    };
}

function createMasterBus(ctx) {
    const highpass = ctx.createBiquadFilter();
    highpass.type = 'highpass';
    highpass.frequency.value = 90;
    highpass.Q.value = 0.7;

    const compressor = ctx.createDynamicsCompressor();
    compressor.threshold.value = -20;
    compressor.knee.value = 8;
    compressor.ratio.value = 2.5;
    compressor.attack.value = 0.005;
    compressor.release.value = 0.15;

    const limiter = ctx.createDynamicsCompressor();
    limiter.threshold.value = -3;
    limiter.knee.value = 0;
    limiter.ratio.value = 20;
    limiter.attack.value = 0.001;
    limiter.release.value = 0.08;

    highpass.connect(compressor);
    compressor.connect(limiter);

    return { input: highpass, output: limiter };
}

function populateDeckSelect() {
    const current = deckSelect.value;
    deckSelect.innerHTML = '<option value="">— Selecciona canción —</option>';
    filteredLibrary.forEach((song, i) => {
        const opt = document.createElement('option');
        opt.value = i;
        const src = song.source === 'folder' ? '📂' : '☁️';
        opt.textContent = `${src} ${song.title}${song.artist ? ' — ' + song.artist : ''}`;
        deckSelect.appendChild(opt);
    });
    if (current) deckSelect.value = current;
    btnDeckPlay.disabled = !filteredLibrary.length;
    btnDeckNext.disabled = filteredLibrary.length < 2;
}

deckSearch.addEventListener('input', () => {
    const q = deckSearch.value.toLowerCase().trim();
    filteredLibrary = LIBRARY.filter(s =>
        s.title.toLowerCase().includes(q) ||
        (s.artist || '').toLowerCase().includes(q) ||
        (s.album || '').toLowerCase().includes(q)
    );
    populateDeckSelect();
});

function initMixer() {
    if (mixerContext) return;

    mixerContext = new AudioContext({ sampleRate: 48000 });
    mixerDestination = mixerContext.createMediaStreamDestination();

    micGain = mixerContext.createGain();
    musicGain = mixerContext.createGain();
    deckGain = mixerContext.createGain();
    monitorGain = mixerContext.createGain();
    masterBus = createMasterBus(mixerContext);

    musicGain.gain.value = 0;
    deckGain.gain.value = deckVol.value / 100;
    monitorGain.gain.value = 0.25;

    micGain.connect(masterBus.input);
    musicGain.connect(masterBus.input);
    deckGain.connect(masterBus.input);
    masterBus.output.connect(mixerDestination);

    analyser = mixerContext.createAnalyser();
    analyser.fftSize = 64;
    masterBus.output.connect(analyser);

    deckGain.connect(monitorGain);
    monitorGain.connect(mixerContext.destination);

    const deckSource = mixerContext.createMediaElementSource(deckPlayer);
    deckSource.connect(deckGain);

    updateMusicRouting();
}

function connectMic(stream) {
    micStream = stream;
    const src = mixerContext.createMediaStreamSource(stream);
    const micFilter = mixerContext.createBiquadFilter();
    micFilter.type = 'highpass';
    micFilter.frequency.value = 120;
    src.connect(micFilter);
    micFilter.connect(micGain);
    updateMicGain();
}

function connectMusicStream(stream, label) {
    if (!mixerContext) initMixer();

    if (musicStream && musicStream !== stream) {
        musicStream.getTracks().forEach(t => t.stop());
        musicConnected = false;
    }

    musicStream = stream;
    const audioTracks = stream.getAudioTracks();
    if (!audioTracks.length) {
        setMusicStatus('Sin audio', 'error');
        log('No se detectó audio en la entrada de música.');
        return false;
    }

    if (!musicConnected) {
        const audioOnly = new MediaStream(audioTracks);
        const src = mixerContext.createMediaStreamSource(audioOnly);
        src.connect(musicGain);
        musicConnected = true;
    }

    updateMusicGain();
    setMusicStatus(label, 'ok');
    log(`Música conectada: ${label}`);
    return true;
}

function connectPcAudio(stream) {
    stream.getVideoTracks().forEach(t => t.stop());
    return connectMusicStream(stream, 'Captura pantalla');
}

function setMusicStatus(text, type = 'idle') {
    musicStatus.textContent = text;
    musicStatus.className = 'text-xs px-2 py-1 rounded ' + ({
        ok: 'bg-emerald-900/50 text-emerald-300',
        error: 'bg-red-900/50 text-red-300',
        idle: 'bg-slate-700 text-slate-400',
    }[type] || 'bg-slate-700 text-slate-400');
}

function updateMusicRouting() {
    if (!deckGain || !musicGain) return;

    if (musicSourceMode === 'deck') {
        deckGain.gain.value = deckVol.value / 100;
        musicGain.gain.value = 0;
        setMusicStatus('Deck local', 'idle');
    } else {
        deckGain.gain.value = 0;
        updateMusicGain();
    }
}

async function loadAudioDevices() {
    try {
        await navigator.mediaDevices.getUserMedia({ audio: true }).then(s => s.getTracks().forEach(t => t.stop()));
    } catch (e) {}

    const devices = await navigator.mediaDevices.enumerateDevices();
    const inputs = devices.filter(d => d.kind === 'audioinput' && d.deviceId !== 'default');

    cableDevice.innerHTML = '<option value="">— Selecciona entrada de audio —</option>';
    inputs.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.deviceId;
        opt.textContent = d.label || `Entrada ${d.deviceId.slice(0, 8)}`;
        cableDevice.appendChild(opt);
    });
}

function updateMicGain() {
    if (!micGain) return;
    micGain.gain.value = micEnabled.checked ? micVol.value / 100 : 0;
}

function updateMusicGain() {
    if (!musicGain) return;
    musicGain.gain.value = (musicSourceMode !== 'deck' && musicConnected) ? musicVol.value / 100 : 0;
}

function updateDeckGain() {
    if (!deckGain) return;
    deckGain.gain.value = deckVol.value / 100;
}

micEnabled.addEventListener('change', updateMicGain);
micVol.addEventListener('input', updateMicGain);
musicVol.addEventListener('input', updateMusicGain);
deckVol.addEventListener('input', () => { updateDeckGain(); updateMusicRouting(); });

musicSourceRadios.forEach(radio => {
    radio.addEventListener('change', () => {
        musicSourceMode = radio.value;
        cablePanel.classList.toggle('hidden', musicSourceMode !== 'cable');
        capturePanel.classList.toggle('hidden', musicSourceMode !== 'capture');
        updateMusicRouting();
    });
});

btnConnectCable.addEventListener('click', async () => {
    try {
        if (!mixerContext) initMixer();
        const deviceId = cableDevice.value;
        if (!deviceId) {
            alert('Selecciona una entrada de audio (VB-Cable, línea, etc.)');
            return;
        }

        const stream = await navigator.mediaDevices.getUserMedia({
            audio: {
                deviceId: { exact: deviceId },
                echoCancellation: false,
                noiseSuppression: false,
                autoGainControl: false,
                sampleRate: 48000,
                channelCount: 2,
            },
            video: false,
        });

        connectMusicStream(stream, cableDevice.selectedOptions[0]?.textContent || 'VB-Cable');
    } catch (err) {
        log('No se pudo conectar la entrada de audio');
    }
});

btnCapturePc.addEventListener('click', async () => {
    try {
        if (!mixerContext) initMixer();

        const stream = await navigator.mediaDevices.getDisplayMedia({
            video: { frameRate: 1 },
            audio: {
                echoCancellation: false,
                noiseSuppression: false,
                autoGainControl: false,
                sampleRate: 48000,
            },
        });

        connectPcAudio(stream);

        stream.getAudioTracks()[0]?.addEventListener('ended', () => {
            musicConnected = false;
            setMusicStatus('Desconectado', 'idle');
            if (musicGain) musicGain.gain.value = 0;
            log('Captura de audio finalizada');
        });
    } catch (err) {
        log('Captura cancelada o no permitida');
    }
});

function loadDeckSong(index) {
    if (index < 0 || index >= filteredLibrary.length) return;
    deckIndex = index;
    const song = filteredLibrary[index];
    deckPlayer.src = song.url;
    deckNow.textContent = `🎵 ${song.title}${song.artist ? ' — ' + song.artist : ''}`;
    deckSelect.value = String(index);
    btnDeckPlay.disabled = false;
    btnDeckPause.disabled = false;
    btnDeckStop.disabled = false;
}

deckSelect.addEventListener('change', () => {
    if (deckSelect.value === '') return;
    loadDeckSong(parseInt(deckSelect.value, 10));
});

btnDeckPlay.addEventListener('click', async () => {
    if (deckIndex < 0 && filteredLibrary.length) loadDeckSong(0);
    if (!deckPlayer.src) return;
    if (!mixerContext) initMixer();
    if (mixerContext.state === 'suspended') await mixerContext.resume();
    deckPlayer.play().catch(() => log('No se pudo reproducir en el deck'));
});

btnDeckPause.addEventListener('click', () => deckPlayer.pause());
btnDeckStop.addEventListener('click', () => { deckPlayer.pause(); deckPlayer.currentTime = 0; });

btnDeckNext.addEventListener('click', () => {
    if (!filteredLibrary.length) return;
    const next = deckIndex < 0 ? 0 : (deckIndex + 1) % filteredLibrary.length;
    loadDeckSong(next);
    deckPlayer.play().catch(() => {});
});

deckPlayer.addEventListener('ended', () => {
    if (!filteredLibrary.length) return;
    const next = (deckIndex + 1) % filteredLibrary.length;
    loadDeckSong(next);
    deckPlayer.play().catch(() => {});
});

async function uploadChunk(blob, mime) {
    if (!isBroadcasting) return false;

    const formData = new FormData();
    const ext = mime.includes('ogg') ? 'ogg' : (mime.includes('mp4') ? 'm4a' : 'webm');
    formData.append('chunk', blob, `chunk.${ext}`);
    formData.append('mime', mime);

    try {
        const r = await fetch('{{ route('admin.live.chunk') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData,
        });

        if (!isBroadcasting) return false;
        const data = await r.json().catch(() => ({}));

        if (r.ok && data.success) {
            chunksSent++;
            chunkCount.textContent = chunksSent;
            if (data.index !== undefined) chunkLatest.textContent = data.index;
            if (chunksSent === 1 || chunksSent % 10 === 0) {
                log(`✓ ${chunksSent} segmentos enviados (${Math.round((data.size || 0) / 1024)} KB)`);
            }
            return true;
        }
        if (isBroadcasting) log(`Error enviando: ${data.error || r.status}`);
    } catch {
        if (isBroadcasting) log('Error de conexión al enviar audio');
    }
    return false;
}

function enqueueUpload(blob, mime) {
    uploadQueue.push({ blob, mime });
    processUploadQueue();
}

async function processUploadQueue() {
    if (uploadBusy || !isBroadcasting) return;
    uploadBusy = true;
    while (uploadQueue.length && isBroadcasting) {
        const { blob, mime } = uploadQueue.shift();
        await uploadChunk(blob, mime);
    }
    uploadBusy = false;
}

function startContinuousRecording(mime) {
    const { interval, bitrate, label } = getRecordingSettings();

    mediaRecorder = new MediaRecorder(mixerDestination.stream, {
        mimeType: mime,
        audioBitsPerSecond: bitrate,
    });

    mediaRecorder.ondataavailable = (e) => {
        if (!isBroadcasting || e.data.size < 10) return;
        enqueueUpload(e.data, mime);
    };

    mediaRecorder.start(interval);
    log(`[v7] Opus filtrado → stream (${interval / 1000}s, ${bitrate / 1000}kbps, ${label})`);
}

function setupVisualizer() {
    const bars = document.querySelectorAll('#mic-visualizer div');
    const data = new Uint8Array(analyser.frequencyBinCount);

    function draw() {
        if (!analyser) return;
        analyser.getByteFrequencyData(data);
        bars.forEach((bar, i) => {
            const val = data[i] || 0;
            bar.style.height = Math.max(8, (val / 255) * 64) + 'px';
        });
        animationId = requestAnimationFrame(draw);
    }
    draw();
}

async function startBroadcast() {
    try {
        initMixer();

        micStream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: musicSourceMode === 'capture',
                autoGainControl: false,
                sampleRate: 48000,
                channelCount: 2,
            },
            video: false,
        });
        connectMic(micStream);

        if (musicStream && musicSourceMode !== 'deck') connectMusicStream(musicStream, musicStatus.textContent);

        if (mixerContext.state === 'suspended') await mixerContext.resume();

        const res = await fetch('{{ route('admin.live.start') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ host_name: hostNameInput.value.trim() }),
        });

        if (!res.ok) throw new Error('No se pudo iniciar la transmisión');

        isBroadcasting = true;
        const mime = getMimeType();
        log(`Formato: ${mime}`);

        startContinuousRecording(mime);
        setupVisualizer();
        startTime = Date.now();
        chunksSent = 0;

        statusIdle.classList.add('hidden');
        statusLive.classList.remove('hidden');
        btnStart.classList.add('hidden');
        btnStop.classList.remove('hidden');
        hostNameInput.disabled = true;

        durationInterval = setInterval(() => {
            const secs = Math.floor((Date.now() - startTime) / 1000);
            durationEl.textContent = `${Math.floor(secs / 60).toString().padStart(2, '0')}:${(secs % 60).toString().padStart(2, '0')}`;
        }, 1000);

        log('Transmisión iniciada — mezclador activo');
        if (musicSourceMode === 'deck' && !deckPlayer.src) {
            log('⚠️ Elige una canción en el Deck y pulsa Play para enviar música');
        }
        if (musicSourceMode === 'deck') {
            log('Modo: Deck local (recomendado)');
        }
    } catch (err) {
        log('Error: ' + (err.message || 'No se pudo acceder al micrófono'));
        alert('Permite el acceso al micrófono para transmitir.');
    }
}

async function stopBroadcast() {
    const wasLocal = isBroadcasting;
    isBroadcasting = false;
    externalBroadcast = false;

    if (wasLocal) {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
        if (micStream) micStream.getTracks().forEach(t => t.stop());
        if (musicStream) musicStream.getTracks().forEach(t => t.stop());
        if (animationId) cancelAnimationFrame(animationId);
        if (durationInterval) clearInterval(durationInterval);
        deckPlayer.pause();
    }

    await fetch('{{ route('admin.live.stop') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    });

    resetBroadcastUI();
    log(wasLocal ? `Transmisión detenida (${chunksSent} segmentos)` : 'Transmisión externa detenida');
}

function resetBroadcastUI() {
    statusIdle.classList.remove('hidden');
    statusLive.classList.add('hidden');
    btnStart.classList.remove('hidden');
    btnStop.classList.add('hidden');
    hostNameInput.disabled = false;
    chunkLatest.textContent = '—';
    chunkCount.textContent = '0';
    document.getElementById('external-live-notice')?.remove();
    externalLiveLogged = false;
}

function applyExternalLiveUI(data) {
    externalBroadcast = true;
    statusIdle.classList.add('hidden');
    statusLive.classList.remove('hidden');
    btnStart.classList.add('hidden');
    btnStop.classList.remove('hidden');
    hostNameInput.disabled = true;

    const latest = data.latest_index ?? -1;
    chunkLatest.textContent = latest >= 0 ? latest : '—';
    chunkCount.textContent = latest >= 0 ? latest + 1 : 0;
    if (data.host_name) hostNameInput.value = data.host_name;

    if (!document.getElementById('external-live-notice')) {
        const notice = document.createElement('p');
        notice.id = 'external-live-notice';
        notice.className = 'text-amber-400 text-sm mt-3';
        notice.textContent = '📡 Transmitiendo desde Emisora Broadcaster (app externa)';
        statusLive.appendChild(notice);
    }

    if (!externalLiveLogged) {
        log('📡 Transmisión activa desde Emisora Broadcaster (app externa)');
        externalLiveLogged = true;
    }
}

async function syncServerLiveStatus() {
    if (isBroadcasting) return;

    try {
        const res = await fetch('/api/live/status');
        const data = await res.json();

        if (data.is_live) {
            applyExternalLiveUI(data);
        } else if (externalBroadcast) {
            externalBroadcast = false;
            resetBroadcastUI();
        }
    } catch (e) {}
}

btnStart.addEventListener('click', startBroadcast);
btnStop.addEventListener('click', stopBroadcast);

window.addEventListener('beforeunload', (e) => {
    if (isBroadcasting) {
        e.preventDefault();
        navigator.sendBeacon('{{ route('admin.live.stop') }}', new URLSearchParams({ _token: '{{ csrf_token() }}' }));
    }
});

populateDeckSelect();
loadAudioDevices();
setInterval(syncServerLiveStatus, 3000);
@if($status['is_live'])
    applyExternalLiveUI(@json($status));
@endif
</script>
@endsection
