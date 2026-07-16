@extends('layouts.admin')

@section('title', 'Mensajes')

@section('content')
<div class="mb-8">
    <h2 class="text-2xl font-bold">Mensajes de oyentes</h2>
    <p class="text-slate-400">Saludos y peticiones de la audiencia</p>
</div>

<div class="space-y-4">
    @forelse($messages as $message)
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 {{ !$message->is_read ? 'border-l-4 border-l-violet-500' : '' }}">
            <div class="flex justify-between items-start">
                <div>
                    <p class="font-semibold">{{ $message->sender_name }}</p>
                    <p class="text-slate-300 mt-2">{{ $message->content }}</p>
                    <p class="text-slate-500 text-xs mt-2">{{ $message->created_at->diffForHumans() }}</p>
                </div>
                <div class="flex gap-2">
                    @if(!$message->is_read)
                        <form action="{{ route('admin.messages.read', $message) }}" method="POST">
                            @csrf @method('PATCH')
                            <button class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded">Leído</button>
                        </form>
                    @endif
                    <form action="{{ route('admin.messages.approval', $message) }}" method="POST">
                        @csrf @method('PATCH')
                        <button class="text-xs {{ $message->is_approved ? 'text-amber-400' : 'text-green-400' }} px-3 py-1">
                            {{ $message->is_approved ? 'Ocultar' : 'Mostrar' }}
                        </button>
                    </form>
                    <form action="{{ route('admin.messages.destroy', $message) }}" method="POST" onsubmit="return confirm('¿Eliminar?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-400 px-3 py-1">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="text-center py-12 text-slate-500">No hay mensajes aún</div>
    @endforelse
</div>

<div class="mt-6">{{ $messages->links() }}</div>
@endsection
