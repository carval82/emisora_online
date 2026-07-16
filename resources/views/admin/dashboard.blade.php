@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="mb-8">
    <h2 class="text-2xl font-bold">Dashboard</h2>
    <p class="text-slate-400">Bienvenido a tu emisora online</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <p class="text-slate-400 text-sm">Canciones</p>
        <p class="text-3xl font-bold text-violet-400">{{ $stats['songs'] }}</p>
    </div>
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <p class="text-slate-400 text-sm">Playlists</p>
        <p class="text-3xl font-bold text-blue-400">{{ $stats['playlists'] }}</p>
    </div>
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <p class="text-slate-400 text-sm">Mensajes sin leer</p>
        <p class="text-3xl font-bold text-amber-400">{{ $stats['unread_messages'] }}</p>
    </div>
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <p class="text-slate-400 text-sm">Estado</p>
        <p class="text-lg font-bold {{ $station->is_live ? 'text-red-400' : 'text-green-400' }}">
            {{ $station->is_live ? '🔴 EN VIVO' : '🟢 Automático' }}
        </p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h3 class="font-semibold mb-4">Configuración de la emisora</h3>
        <form action="{{ route('admin.station.update') }}" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm text-slate-400 mb-1">Nombre</label>
                <input type="text" name="name" value="{{ $station->name }}" required
                    class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">Eslogan</label>
                <input type="text" name="slogan" value="{{ $station->slogan }}"
                    class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-400">
                <span>Estado EN VIVO se controla desde el estudio de transmisión</span>
            </label>
            <a href="{{ route('admin.live.index') }}" class="inline-block bg-red-600 hover:bg-red-500 px-4 py-2 rounded-lg text-sm font-medium">
                🎙️ Ir al estudio en vivo
            </a>
            <button type="submit" class="block bg-violet-600 hover:bg-violet-500 px-4 py-2 rounded-lg text-sm font-medium">
                Guardar
            </button>
        </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h3 class="font-semibold mb-4">Últimos mensajes</h3>
        @forelse($recentMessages as $msg)
            <div class="border-b border-slate-800 py-3 last:border-0">
                <p class="font-medium text-sm">{{ $msg->sender_name }}</p>
                <p class="text-slate-400 text-sm">{{ Str::limit($msg->content, 80) }}</p>
            </div>
        @empty
            <p class="text-slate-500 text-sm">No hay mensajes aún</p>
        @endforelse
    </div>
</div>

<div class="mt-6 flex gap-3">
    <a href="{{ route('admin.songs.create') }}" class="bg-violet-600 hover:bg-violet-500 px-4 py-2 rounded-lg text-sm font-medium">+ Subir canciones</a>
    <a href="{{ route('admin.playlists.create') }}" class="bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg text-sm font-medium">+ Nueva playlist</a>
    <a href="{{ route('admin.live.index') }}" class="bg-red-600 hover:bg-red-500 px-4 py-2 rounded-lg text-sm font-medium">🎙️ Transmitir en vivo</a>
</div>

<div class="mt-6 bg-slate-900 border border-emerald-800/50 rounded-xl p-6">
    <h3 class="font-semibold mb-2 flex items-center gap-2">
        <span>📡</span> Escuchar desde otro equipo (red local)
    </h3>
    <p class="text-sm text-slate-400 mb-4">
        Inicia el servidor con <code class="text-emerald-300">serve-lan.bat</code> y abre estas URLs desde el celular, tablet u otro PC en la misma red.
    </p>

    @if(count($lanAddresses))
        <div class="space-y-2">
            @foreach($lanAddresses as $ip)
                <div class="flex flex-wrap items-center gap-3 bg-slate-800 rounded-lg px-4 py-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-slate-500">Emisora (oyente)</p>
                        <a href="http://{{ $ip }}:{{ $lanPort }}" target="_blank" class="text-emerald-300 hover:text-emerald-200 font-mono text-sm break-all">
                            http://{{ $ip }}:{{ $lanPort }}
                        </a>
                    </div>
                    <a href="http://{{ $ip }}:{{ $lanPort }}" target="_blank" class="bg-emerald-700 hover:bg-emerald-600 px-3 py-1.5 rounded-lg text-xs font-medium">
                        Abrir
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-amber-300">No se detectó IP de red. Usa <code class="text-emerald-300">serve-lan.bat</code> y revisa tu IP con <code>ipconfig</code>.</p>
    @endif

    <ul class="text-xs text-slate-500 mt-4 space-y-1 list-disc list-inside">
        <li>Este PC = estudio DJ y admin. El otro equipo = solo oyente.</li>
        <li>Ambos deben estar en la misma WiFi o red cableada.</li>
        <li>Si no carga, permite el puerto {{ $lanPort }} en el firewall de Windows.</li>
    </ul>
</div>
@endsection
