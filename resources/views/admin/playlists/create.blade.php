@extends('layouts.admin')

@section('title', 'Nueva playlist')

@section('content')
<div class="mb-8">
    <h2 class="text-2xl font-bold">Nueva playlist</h2>
</div>

<form action="{{ route('admin.playlists.store') }}" method="POST" class="max-w-xl bg-slate-900 border border-slate-800 rounded-xl p-8 space-y-6">
    @csrf

    <div>
        <label class="block text-sm text-slate-400 mb-2">Nombre *</label>
        <input type="text" name="name" value="{{ old('name') }}" required
            class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3">
    </div>

    <div>
        <label class="block text-sm text-slate-400 mb-2">Descripción</label>
        <textarea name="description" rows="3" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3">{{ old('description') }}</textarea>
    </div>

    <label class="flex items-center gap-2">
        <input type="checkbox" name="shuffle" value="1" class="rounded bg-slate-800 border-slate-700">
        <span class="text-sm">Reproducir en orden aleatorio</span>
    </label>

    <label class="flex items-center gap-2">
        <input type="checkbox" name="is_default" value="1" class="rounded bg-slate-800 border-slate-700">
        <span class="text-sm">Playlist por defecto</span>
    </label>

    <div class="flex gap-3">
        <button type="submit" class="bg-violet-600 hover:bg-violet-500 px-6 py-2 rounded-lg font-medium">Crear</button>
        <a href="{{ route('admin.playlists.index') }}" class="bg-slate-700 hover:bg-slate-600 px-6 py-2 rounded-lg">Cancelar</a>
    </div>
</form>
@endsection
