<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#7c3aed">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/manifest.json">
    <title>{{ $station->name }} — Emisora Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .gradient-bg {
            background: radial-gradient(ellipse at top, #1e1b4b 0%, #0f0a1a 50%, #030712 100%);
        }
        .vinyl {
            animation: spin 4s linear infinite;
            animation-play-state: paused;
        }
        .vinyl.playing { animation-play-state: running; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .pulse-live { animation: pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .visualizer span {
            display: inline-block;
            width: 4px;
            background: #a78bfa;
            border-radius: 2px;
            animation: bars 0.8s ease-in-out infinite alternate;
        }
        .visualizer span:nth-child(2) { animation-delay: 0.1s; }
        .visualizer span:nth-child(3) { animation-delay: 0.2s; }
        .visualizer span:nth-child(4) { animation-delay: 0.3s; }
        .visualizer span:nth-child(5) { animation-delay: 0.4s; }
        @keyframes bars { from { height: 8px; } to { height: 28px; } }
        .visualizer.paused span { animation: none; height: 8px; }
    </style>
</head>
<body class="gradient-bg text-white min-h-screen">
    <div class="max-w-lg mx-auto min-h-screen flex flex-col p-6">
        <!-- Header -->
        <header class="text-center mb-8">
            <div id="live-badge" class="hidden mb-3">
                <span class="inline-flex items-center gap-2 bg-red-600/80 px-4 py-1 rounded-full text-sm font-medium pulse-live">
                    <span class="w-2 h-2 bg-white rounded-full"></span> EN VIVO
                </span>
            </div>
            <h1 id="station-name" class="text-3xl font-bold tracking-tight">{{ $station->name }}</h1>
            <p id="station-slogan" class="text-violet-300/70 mt-1 text-sm">{{ $station->slogan }}</p>
        </header>

        <!-- Player -->
        <div class="flex-1 flex flex-col items-center justify-center">
            <div class="relative mb-8">
                <div id="vinyl" class="vinyl w-56 h-56 rounded-full bg-gradient-to-br from-violet-900 to-slate-900 border-4 border-violet-500/30 flex items-center justify-center shadow-2xl shadow-violet-900/50">
                    <div class="w-20 h-20 rounded-full bg-slate-950 border-2 border-violet-400/50 flex items-center justify-center">
                        <span id="vinyl-icon" class="text-3xl">📻</span>
                    </div>
                </div>
                <div id="visualizer" class="visualizer paused absolute -bottom-4 left-1/2 -translate-x-1/2 flex gap-1 items-end h-7">
                    <span></span><span></span><span></span><span></span><span></span>
                </div>
            </div>

            <div class="text-center mb-8 w-full">
                <p id="now-playing" class="text-xl font-semibold truncate px-4">Cargando...</p>
                <p id="now-artist" class="text-violet-300/60 text-sm mt-1 truncate px-4">—</p>
                <p id="playlist-name" class="text-slate-500 text-xs mt-2"></p>
            </div>

            <div class="flex items-center gap-6 mb-8">
                <button id="btn-prev" class="w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition" title="Anterior">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6V6zm3.5 6l8.5 6V6l-8.5 6z"/></svg>
                </button>
                <button id="btn-play" class="w-16 h-16 rounded-full bg-violet-600 hover:bg-violet-500 flex items-center justify-center shadow-lg shadow-violet-600/40 transition" title="Play/Pause">
                    <svg id="icon-play" class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7L8 5z"/></svg>
                    <svg id="icon-pause" class="w-8 h-8 hidden" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                </button>
                <button id="btn-next" class="w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition" title="Siguiente">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zm2-6v0zm0 0l8.5 6V6L8 12z"/><path d="M16 6v12h2V6h-2z"/></svg>
                </button>
            </div>

            <div class="w-full bg-white/10 rounded-full h-1.5 mb-2">
                <div id="progress" class="bg-violet-500 h-1.5 rounded-full transition-all" style="width: 0%"></div>
            </div>
            <div class="flex justify-between text-xs text-slate-500 w-full mb-6">
                <span id="time-current">0:00</span>
                <span id="time-total">0:00</span>
            </div>
        </div>

        <!-- Messages -->
        <section class="bg-white/5 backdrop-blur border border-white/10 rounded-2xl p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-sm flex items-center gap-2">
                    💬 Mensajes
                    <span id="msg-count" class="text-xs bg-violet-600/60 px-2 py-0.5 rounded-full min-w-[1.5rem] text-center">{{ $messages->count() }}</span>
                </h3>
                <span class="text-[10px] text-slate-500 uppercase tracking-wide">Saludos en vivo</span>
            </div>

            <div id="messages-list" class="min-h-[88px] max-h-44 overflow-y-auto space-y-2 mb-4 text-sm pr-1 scroll-smooth">
                @forelse($messages as $msg)
                    <article class="bg-white/5 border border-white/5 rounded-xl px-3 py-2.5">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="font-medium text-violet-300 truncate">{{ $msg->sender_name }}</span>
                            <time class="text-[10px] text-slate-500 shrink-0">{{ $msg->created_at->diffForHumans() }}</time>
                        </div>
                        <p class="text-slate-200 mt-1 break-words leading-snug">{{ $msg->content }}</p>
                    </article>
                @empty
                    <p id="messages-empty" class="text-center text-slate-500 text-sm py-6">Sé el primero en saludar 👋</p>
                @endforelse
            </div>

            <form id="message-form" class="space-y-2">
                <input type="text" id="sender-name" placeholder="Tu nombre" maxlength="100" required autocomplete="nickname"
                    class="w-full bg-white/10 border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500/40">
                <textarea id="message-content" placeholder="Escribe un mensaje para la emisora..." maxlength="500" required rows="2"
                    class="w-full bg-white/10 border border-white/10 rounded-xl px-3 py-2.5 text-sm resize-none focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500/40"></textarea>
                <button type="submit" id="message-submit"
                    class="w-full bg-violet-600 hover:bg-violet-500 active:bg-violet-700 px-4 py-2.5 rounded-xl text-sm font-semibold transition">
                    Enviar mensaje
                </button>
            </form>
        </section>

        <footer class="text-center text-slate-600 text-xs py-2 space-x-3">
            <a href="/app/" class="hover:text-violet-400">App oyente</a>
            <span>·</span>
            <a href="/admin" class="hover:text-violet-400">Admin</a>
        </footer>
    </div>

    <audio id="audio" preload="auto"></audio>

    <div id="tap-overlay" class="hidden fixed inset-x-4 bottom-24 z-40 bg-violet-700/95 border border-violet-400/40 text-white text-sm font-medium text-center px-4 py-3 rounded-2xl shadow-xl cursor-pointer">
        Toca aquí para escuchar en vivo 🔊
    </div>

    <script>
        const audio = document.getElementById('audio');
        const btnPlay = document.getElementById('btn-play');
        const btnPrev = document.getElementById('btn-prev');
        const btnNext = document.getElementById('btn-next');
        const iconPlay = document.getElementById('icon-play');
        const iconPause = document.getElementById('icon-pause');
        const vinyl = document.getElementById('vinyl');
        const visualizer = document.getElementById('visualizer');
        const progress = document.getElementById('progress');
        const nowPlaying = document.getElementById('now-playing');
        const nowArtist = document.getElementById('now-artist');

        let queue = [];
        let currentIndex = 0;
        let isPlaying = false;
        let liveMode = false;
        let livePollTimer = null;
        let liveHostName = '';
        let stationIsLive = false;
        let stationLiveHost = '';
        let liveNeedsGesture = false;
        let liveStarted = false;
        let liveLoopActive = false;
        let audioUnlocked = false;
        let autoLiveLock = false;

        function teardownLive() {
            liveNeedsGesture = false;
            liveStarted = false;
            liveLoopActive = false;

            if (livePollTimer) { clearTimeout(livePollTimer); livePollTimer = null; }

            audio.pause();
            audio.removeAttribute('src');
            audio.load();
        }

        function showTapOverlay() {
            document.getElementById('tap-overlay').classList.remove('hidden');
        }

        function hideTapOverlay() {
            document.getElementById('tap-overlay').classList.add('hidden');
        }

        async function tryPlayLiveAudio() {
            try {
                audio.muted = true;
                await audio.play();
                audio.muted = false;
                liveStarted = true;
                setPlaying(true);
                liveNeedsGesture = false;
                hideTapOverlay();
                nowArtist.textContent = liveHostName;
                return true;
            } catch (e) {
                liveNeedsGesture = true;
                showTapOverlay();
                nowArtist.textContent = liveHostName;
                return false;
            }
        }

        async function autoJoinLive(data = {}) {
            if (liveMode || autoLiveLock) return;
            autoLiveLock = true;
            try {
                await unlockAudioElement();
                enterLiveMode({ host_name: data.host_name || stationLiveHost });
            } finally {
                autoLiveLock = false;
            }
        }

        function scheduleLiveMonitor() {
            if (!liveMode || !liveLoopActive) return;
            if (livePollTimer) clearTimeout(livePollTimer);
            livePollTimer = setTimeout(async () => {
                if (!liveMode) return;
                try {
                    const data = await (await fetch('/api/live/status')).json();
                    if (!data.is_live) {
                        exitLiveMode();
                        return;
                    }
                } catch (e) {}
                scheduleLiveMonitor();
            }, 10000);
        }

        async function resumeLivePlayback() {
            try {
                await tryPlayLiveAudio();
            } catch (e) {
                nowArtist.textContent = 'Toca aquí abajo para escuchar 🔊';
                showTapOverlay();
                liveNeedsGesture = true;
            }
        }

        async function startLiveStream() {
            nowArtist.textContent = 'Conectando...';
            liveLoopActive = true;

            try {
                const status = await (await fetch('/api/live/status')).json();
                liveHostName = status.host_name || liveHostName;
                audio.src = `/api/live/stream?live=1&_=${Date.now()}`;
                audio.load();
                await tryPlayLiveAudio();
                scheduleLiveMonitor();
            } catch (e) {
                nowArtist.textContent = 'Error al conectar — recarga la página';
            }
        }

        function unlockAudioElement() {
            if (audioUnlocked) return Promise.resolve();
            const prev = audio.src;
            audio.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAAB9hAAAgABAAE';
            return audio.play().then(() => {
                audio.pause();
                audio.removeAttribute('src');
                if (prev) audio.src = prev;
                audioUnlocked = true;
            }).catch(() => {
                audioUnlocked = true;
            });
        }

        async function checkLiveStatus() {
            try {
                const res = await fetch('/api/station');
                const data = await res.json();
                stationIsLive = !!data.is_live;
                stationLiveHost = data.host_name || stationLiveHost;

                if (data.is_live && liveMode) return;

                if (data.is_live && !liveMode && isPlaying) {
                    await autoJoinLive({ host_name: data.host_name });
                } else if (!data.is_live && liveMode) {
                    exitLiveMode();
                } else if (data.is_live && !liveMode) {
                    document.getElementById('live-badge').classList.remove('hidden');
                    await autoJoinLive({ host_name: data.host_name });
                } else {
                    document.getElementById('live-badge').classList.add('hidden');
                }
            } catch (e) {}
        }

        function enterLiveMode(data) {
            liveMode = true;
            liveHostName = data.host_name || 'En directo';
            teardownLive();
            startLiveStream();
            document.getElementById('live-badge').classList.remove('hidden');
            document.getElementById('vinyl-icon').textContent = '🎙️';
            nowPlaying.textContent = 'Transmisión en vivo';
            document.getElementById('playlist-name').textContent = '🔴 EN VIVO';
            progress.style.width = '100%';
            document.getElementById('time-current').textContent = 'EN';
            document.getElementById('time-total').textContent = 'VIVO';
            vinyl.classList.add('playing');
            visualizer.classList.remove('paused');
            btnPrev.disabled = true;
            btnNext.disabled = true;
            btnPrev.classList.add('opacity-30');
            btnNext.classList.add('opacity-30');
        }

        function exitLiveMode() {
            liveMode = false;
            teardownLive();
            document.getElementById('live-badge').classList.add('hidden');
            document.getElementById('vinyl-icon').textContent = '📻';
            btnPrev.disabled = false;
            btnNext.disabled = false;
            btnPrev.classList.remove('opacity-30');
            btnNext.classList.remove('opacity-30');
            loadQueue().then(() => {
                if (queue.length) playTrack(currentIndex);
                else setPlaying(false);
            });
        }

        async function loadStation() {
            const res = await fetch('/api/station');
            const data = await res.json();
            document.getElementById('station-name').textContent = data.name;
            document.getElementById('station-slogan').textContent = data.slogan || '';
            stationIsLive = !!data.is_live;
            stationLiveHost = data.host_name || 'Locutor en vivo';
            if (stationIsLive && !liveMode) {
                document.getElementById('live-badge').classList.remove('hidden');
                nowArtist.textContent = 'Conectando en vivo...';
                await autoJoinLive({ host_name: data.host_name });
            } else if (!stationIsLive) {
                document.getElementById('live-badge').classList.add('hidden');
            }
        }

        async function loadQueue() {
            if (liveMode) return;
            const res = await fetch('/api/queue');
            const data = await res.json();
            queue = data.songs || [];
            document.getElementById('playlist-name').textContent = data.playlist ? `📋 ${data.playlist}` : '';
            if (queue.length > 0 && !liveMode && !stationIsLive && !audio.src) {
                playTrack(0);
            } else if (queue.length === 0 && !liveMode) {
                nowPlaying.textContent = 'Sin canciones';
                nowArtist.textContent = 'Agrega música desde el panel admin';
            }
        }

        function playTrack(index) {
            if (liveMode || !queue.length) return;
            currentIndex = index % queue.length;
            const track = queue[currentIndex];
            audio.src = track.url;
            audio.load();
            nowPlaying.textContent = track.title;
            nowArtist.textContent = track.artist || 'Artista desconocido';
            audio.play().then(() => setPlaying(true)).catch(() => setPlaying(false));
        }

        function setPlaying(playing) {
            isPlaying = playing;
            iconPlay.classList.toggle('hidden', playing);
            iconPause.classList.toggle('hidden', !playing);
            vinyl.classList.toggle('playing', playing);
            visualizer.classList.toggle('paused', !playing);
        }

        function formatTime(s) {
            const m = Math.floor(s / 60);
            const sec = Math.floor(s % 60);
            return `${m}:${sec.toString().padStart(2, '0')}`;
        }

        btnPlay.addEventListener('click', () => {
            if (stationIsLive && !liveMode) {
                autoJoinLive({ host_name: stationLiveHost });
                return;
            }
            if (liveMode) {
                if (!liveStarted || audio.paused || liveNeedsGesture) {
                    tryPlayLiveAudio().then((ok) => {
                        if (!ok) resumeLivePlayback();
                    });
                } else {
                    audio.pause();
                    setPlaying(false);
                }
                return;
            }
            if (!queue.length) return;
            if (isPlaying) { audio.pause(); setPlaying(false); }
            else { audio.play().then(() => setPlaying(true)); }
        });

        btnNext.addEventListener('click', () => { if (!liveMode) playTrack(currentIndex + 1); });
        btnPrev.addEventListener('click', () => { if (!liveMode) playTrack(currentIndex - 1 + queue.length); });

        audio.addEventListener('ended', () => {
            if (liveMode) return;
            playTrack(currentIndex + 1);
        });
        audio.addEventListener('timeupdate', () => {
            if (liveMode) return;
            if (audio.duration) {
                progress.style.width = (audio.currentTime / audio.duration * 100) + '%';
                document.getElementById('time-current').textContent = formatTime(audio.currentTime);
                document.getElementById('time-total').textContent = formatTime(audio.duration);
            }
        });

        function resumeLiveOnGesture() {
            if (liveMode && liveNeedsGesture) {
                tryPlayLiveAudio().then((ok) => {
                    if (!ok) resumeLivePlayback();
                });
            }
        }

        document.body.addEventListener('click', resumeLiveOnGesture, { once: false });
        document.getElementById('tap-overlay').addEventListener('click', resumeLiveOnGesture);

        audio.addEventListener('error', () => {
            if (!liveMode || liveNeedsGesture) return;
            nowArtist.textContent = 'Reconectando en vivo...';
            setTimeout(() => {
                if (!liveMode || liveLoopActive === false) return;
                audio.src = `/api/live/stream?live=1&_=${Date.now()}`;
                audio.load();
                tryPlayLiveAudio();
            }, 1500);
        });

        audio.addEventListener('waiting', () => {
            if (liveMode) {
                nowArtist.textContent = 'Buffering en vivo...';
                return;
            }
            setPlaying(false);
        });
        audio.addEventListener('stalled', () => {
            if (liveMode && liveLoopActive && !liveNeedsGesture) {
                nowArtist.textContent = 'Reconectando en vivo...';
                const pos = audio.src;
                audio.src = '';
                audio.load();
                setTimeout(() => {
                    if (!liveMode) return;
                    audio.src = `/api/live/stream?live=1&_=${Date.now()}`;
                    audio.load();
                    tryPlayLiveAudio();
                }, 800);
            }
        });
        audio.addEventListener('playing', () => {
            if (liveMode) setPlaying(true);
        });

        document.getElementById('message-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('sender-name').value.trim();
            const content = document.getElementById('message-content').value.trim();
            if (!name || !content) return;

            const btn = document.getElementById('message-submit');
            btn.disabled = true;
            btn.textContent = 'Enviando...';

            try {
                const res = await fetch('/api/messages', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ sender_name: name, content }),
                });

                if (res.ok) {
                    const data = await res.json();
                    localStorage.setItem('emisora_sender_name', name);
                    prependMessage(data.message);
                    document.getElementById('message-content').value = '';
                }
            } finally {
                btn.disabled = false;
                btn.textContent = 'Enviar mensaje';
            }
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function renderMessage(m) {
            return `<article class="bg-white/5 border border-white/5 rounded-xl px-3 py-2.5">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="font-medium text-violet-300 truncate">${escapeHtml(m.sender_name)}</span>
                    <time class="text-[10px] text-slate-500 shrink-0">${escapeHtml(m.created_at || 'ahora')}</time>
                </div>
                <p class="text-slate-200 mt-1 break-words leading-snug">${escapeHtml(m.content)}</p>
            </article>`;
        }

        function prependMessage(m) {
            const list = document.getElementById('messages-list');
            const empty = document.getElementById('messages-empty');
            if (empty) empty.remove();
            list.insertAdjacentHTML('afterbegin', renderMessage(m));
            document.getElementById('msg-count').textContent = list.querySelectorAll('article').length;
        }

        async function refreshMessages() {
            const res = await fetch('/api/messages');
            const data = await res.json();
            const list = document.getElementById('messages-list');
            if (!data.messages.length) {
                list.innerHTML = '<p id="messages-empty" class="text-center text-slate-500 text-sm py-6">Sé el primero en saludar 👋</p>';
            } else {
                list.innerHTML = data.messages.map(renderMessage).join('');
            }
            document.getElementById('msg-count').textContent = data.messages.length;
        }

        async function bootstrap() {
            await loadStation();
            if (!liveMode && !stationIsLive) {
                await loadQueue();
            }
            scheduleStationPoll();
            scheduleMessagesPoll();
        }

        function scheduleStationPoll() {
            setTimeout(async () => {
                await checkLiveStatus();
                scheduleStationPoll();
            }, liveMode ? 10000 : 4000);
        }

        function scheduleMessagesPoll() {
            setTimeout(async () => {
                await refreshMessages();
                scheduleMessagesPoll();
            }, liveMode ? 30000 : 15000);
        }

        const savedName = localStorage.getItem('emisora_sender_name');
        if (savedName) document.getElementById('sender-name').value = savedName;

        bootstrap();

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        }
    </script>
</body>
</html>
