<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Emisora Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-slate-900 border-r border-slate-800 p-6 hidden md:block">
            <div class="mb-8">
                <h1 class="text-xl font-bold text-violet-400">📻 Emisora</h1>
                <p class="text-xs text-slate-500 mt-1">Panel de administración</p>
            </div>
            <nav class="space-y-1">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.dashboard') ? 'bg-violet-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                    <span>📊</span> Dashboard
                </a>
                <a href="{{ route('admin.songs.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.songs.*') ? 'bg-violet-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                    <span>🎵</span> Canciones
                </a>
                <a href="{{ route('admin.local.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.local.*') ? 'bg-violet-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                    <span>📂</span> Carpeta local
                </a>
                <a href="{{ route('admin.playlists.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.playlists.*') ? 'bg-violet-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                    <span>📋</span> Playlists
                </a>
                <a href="{{ route('admin.messages.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.messages.*') ? 'bg-violet-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                    <span>💬</span> Mensajes
                </a>
                <a href="{{ route('admin.live.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.live.*') ? 'bg-red-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                    <span>🎙️</span> En vivo
                </a>
                <a href="{{ route('player') }}" target="_blank" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-800 hover:text-white">
                    <span>🎧</span> Ver emisora
                </a>
            </nav>
            <form action="{{ route('admin.logout') }}" method="POST" class="mt-8">
                @csrf
                <button type="submit" class="w-full text-left flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-red-900/30 hover:text-red-400">
                    <span>🚪</span> Cerrar sesión
                </button>
            </form>
        </aside>

        <main class="flex-1 p-6 md:p-8 overflow-auto">
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-900/50 border border-green-700 text-green-300 rounded-lg">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-6 p-4 bg-red-900/50 border border-red-700 text-red-300 rounded-lg">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
