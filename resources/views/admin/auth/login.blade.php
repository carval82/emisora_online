<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Emisora Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-violet-400">📻 Emisora Online</h1>
            <p class="text-slate-400 mt-2">Inicia sesión en el panel admin</p>
        </div>

        <form method="POST" action="{{ route('admin.login') }}" class="bg-slate-900 border border-slate-800 rounded-2xl p-8 space-y-6">
            @csrf

            <div>
                <label class="block text-sm text-slate-400 mb-2">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-violet-500">
                @error('email')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm text-slate-400 mb-2">Contraseña</label>
                <input type="password" name="password" required
                    class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-violet-500">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-400">
                <input type="checkbox" name="remember" class="rounded bg-slate-800 border-slate-700">
                Recordarme
            </label>

            <button type="submit" class="w-full bg-violet-600 hover:bg-violet-500 text-white font-semibold py-3 rounded-lg transition">
                Entrar
            </button>
        </form>

        <p class="text-center mt-4">
            <a href="{{ route('player') }}" class="text-violet-400 text-sm hover:text-violet-300">← Volver a la emisora</a>
        </p>
    </div>
</body>
</html>
