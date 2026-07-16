@extends('layouts.admin')

@section('title', 'Editar playlist')

@section('content')
<div class="mb-8">
    <h2 class="text-2xl font-bold">{{ $playlist->name }}</h2>
    <p class="text-slate-400">Edita la playlist y agrega canciones</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h3 class="font-semibold mb-4">Configuración</h3>
        <form action="{{ route('admin.playlists.update', $playlist) }}" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm text-slate-400 mb-1">Nombre</label>
                <input type="text" name="name" value="{{ $playlist->name }}" required
                    class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">Descripción</label>
                <textarea name="description" rows="2" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2">{{ $playlist->description }}</textarea>
            </div>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="shuffle" value="1" {{ $playlist->shuffle ? 'checked' : '' }} class="rounded">
                <span class="text-sm">Aleatorio</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_default" value="1" {{ $playlist->is_default ? 'checked' : '' }} class="rounded">
                <span class="text-sm">Por defecto</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ $playlist->is_active ? 'checked' : '' }} class="rounded">
                <span class="text-sm">Activa</span>
            </label>
            <button type="submit" class="bg-violet-600 hover:bg-violet-500 px-4 py-2 rounded-lg text-sm">Guardar</button>
        </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h3 class="font-semibold mb-4">Agregar canción</h3>
        @if($availableSongs->count())
            <form action="{{ route('admin.playlists.songs.add', $playlist) }}" method="POST" class="flex gap-2">
                @csrf
                <select name="song_id" class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-4 py-2">
                    @foreach($availableSongs as $song)
                        <option value="{{ $song->id }}">{{ $song->title }} — {{ $song->artist }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-violet-600 hover:bg-violet-500 px-4 py-2 rounded-lg text-sm">Agregar</button>
            </form>
        @else
            <p class="text-slate-500 text-sm">No hay más canciones disponibles. <a href="{{ route('admin.songs.create') }}" class="text-violet-400">Sube más</a></p>
        @endif
    </div>
</div>

<div class="mt-6 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
    <h3 class="font-semibold p-6 border-b border-slate-800">Canciones en la playlist ({{ $playlist->songs->count() }})</h3>
    @forelse($playlist->songs as $index => $song)
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-800 last:border-0">
            <div class="flex items-center gap-4">
                <span class="text-slate-500 text-sm w-6">{{ $index + 1 }}</span>
                <div>
                    <p class="font-medium">{{ $song->title }}</p>
                    <p class="text-slate-400 text-sm">{{ $song->artist ?? 'Artista desconocido' }}</p>
                </div>
            </div>
            <form action="{{ route('admin.playlists.songs.remove', [$playlist, $song]) }}" method="POST">
                @csrf @method('DELETE')
                <button class="text-red-400 hover:text-red-300 text-sm">Quitar</button>
            </form>
        </div>
    @empty
        <p class="p-6 text-slate-500 text-sm">Playlist vacía. Agrega canciones arriba.</p>
    @endforelse
</div>
@endsection
