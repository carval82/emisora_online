<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LiveStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BroadcasterApiController extends Controller
{
    public function __construct(private LiveStreamService $live) {}

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::validate($credentials)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        $user = \App\Models\User::where('email', $credentials['email'])->firstOrFail();
        $token = Str::random(48);

        $user->forceFill([
            'broadcast_token' => hash('sha256', $token),
        ])->save();

        return response()->json([
            'success' => true,
            'token' => $token,
            'name' => $user->name,
        ]);
    }

    public function status()
    {
        return response()->json([
            'success' => true,
            'status' => $this->live->getStatus(),
        ]);
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'host_name' => 'nullable|string|max:100',
        ]);

        $this->live->start($validated['host_name'] ?? auth()->user()?->name);

        return response()->json([
            'success' => true,
            'message' => 'Transmisión iniciada',
            'status' => $this->live->getStatus(),
        ]);
    }

    public function stop()
    {
        $this->live->stop();

        return response()->json([
            'success' => true,
            'message' => 'Transmisión detenida',
        ]);
    }

    public function chunk(Request $request)
    {
        if (! $this->live->isActive()) {
            return response()->json(['error' => 'No hay transmisión activa'], 403);
        }

        $mime = $request->header('Content-Type', 'audio/webm');
        $mime = explode(';', $mime)[0];
        $content = $request->getContent();

        if (strlen($content) < 10 && $request->hasFile('chunk')) {
            $file = $request->file('chunk');
            if (! $file->isValid()) {
                return response()->json(['error' => 'Archivo de audio inválido'], 422);
            }
            $mime = $request->input('mime', $file->getMimeType() ?: 'audio/webm');
            $content = file_get_contents($file->getRealPath());
        }

        if (strlen($content) < 10) {
            return response()->json(['error' => 'No se recibió audio'], 422);
        }

        $index = $this->live->addChunk($content, $mime);
        $live = $this->live;

        app()->terminating(function () use ($index, $live) {
            $live->finalizeChunk($index);
        });

        return response()->json([
            'success' => true,
            'index' => $index,
            'size' => strlen($content),
        ]);
    }
}
