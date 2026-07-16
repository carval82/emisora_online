@extends('layouts.admin')

@section('title', 'Playlists')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h2 class="text-2xl font-bold">Playlists</h2>
        <p class="text-slate-400">Organiza la programación musical</p>
    </div>
    <a href="{{ route('admin.playlists.create') }}" class="bg-violet-600 hover:bg-violet-500 px-4 py-2 rounded-lg text-sm font-medium">
        + Nueva playlist
    </a>
</div>

<div class="grid gap-4">
    @forelse($playlists as $playlist)
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 flex justify-between items-center">
            <div>
                <h3 class="font-semibold text-lg">
                    {{ $playlist->name }}
                    @if($playlist->is_default)<span class="text-xs bg-violet-900/50 text-violet-400 px-2 py-0.5 rounded ml-2">Default</span>@endif
                </h3>
                <p class="text-slate-400 text-sm mt-1">{{ $playlist->songs_count }} canciones · {{ $playlist->shuffle ? 'Aleatorio' : 'En orden' }}</p>
            </div>
            <div class="flex gap-2">
                <form action="{{ route('admin.playlists.activate', $playlist) }}" method="POST">
                    @csrf
                    <button class="text-sm bg-green-900/50 text-green-400 hover:bg-green-900 px-3 py-1.5 rounded">Activar</button>
                </form>
                <a href="{{ route('admin.playlists.edit', $playlist) }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded">Editar</a>
                <form action="{{ route('admin.playlists.destroy', $playlist) }}" method="POST" onsubmit="return confirm('¿Eliminar playlist?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-red-400 hover:text-red-300 px-3 py-1.5">Eliminar</button>
                </form>
            </div>
        </div>
    @empty
        <div class="text-center py-12 text-slate-500">
            No hay playlists. <a href="{{ route('admin.playlists.create') }}" class="text-violet-400">Crea una</a>
        </div>
    @endforelse
</div>
@endsection
