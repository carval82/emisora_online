@extends('layouts.admin')

@section('title', 'Canciones')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h2 class="text-2xl font-bold">Canciones</h2>
        <p class="text-slate-400">Gestiona tu biblioteca musical</p>
    </div>
    <a href="{{ route('admin.songs.create') }}" class="bg-violet-600 hover:bg-violet-500 px-4 py-2 rounded-lg text-sm font-medium">
        + Subir canciones
    </a>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
    <table class="w-full">
        <thead class="bg-slate-800/50">
            <tr>
                <th class="text-left px-6 py-3 text-sm text-slate-400">Título</th>
                <th class="text-left px-6 py-3 text-sm text-slate-400">Artista</th>
                <th class="text-left px-6 py-3 text-sm text-slate-400 hidden md:table-cell">Álbum</th>
                <th class="text-left px-6 py-3 text-sm text-slate-400 hidden lg:table-cell">Duración</th>
                <th class="text-left px-6 py-3 text-sm text-slate-400">Estado</th>
                <th class="text-right px-6 py-3 text-sm text-slate-400">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($songs as $song)
                <tr class="border-t border-slate-800">
                    <td class="px-6 py-4 font-medium">{{ $song->title }}</td>
                    <td class="px-6 py-4 text-slate-400">{{ $song->artist ?? '—' }}</td>
                    <td class="px-6 py-4 text-slate-400 hidden md:table-cell">{{ $song->album ?? '—' }}</td>
                    <td class="px-6 py-4 text-slate-400 hidden lg:table-cell">{{ $song->duration ? $song->formatted_duration : '—' }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-xs {{ $song->is_active ? 'bg-green-900/50 text-green-400' : 'bg-red-900/50 text-red-400' }}">
                            {{ $song->is_active ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <form action="{{ route('admin.songs.toggle', $song) }}" method="POST" class="inline">
                            @csrf @method('PATCH')
                            <button class="text-sm text-amber-400 hover:text-amber-300">Toggle</button>
                        </form>
                        <form action="{{ route('admin.songs.destroy', $song) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar canción?')">
                            @csrf @method('DELETE')
                            <button class="text-sm text-red-400 hover:text-red-300">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                        No hay canciones. <a href="{{ route('admin.songs.create') }}" class="text-violet-400">Sube la primera</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $songs->links() }}</div>
@endsection
