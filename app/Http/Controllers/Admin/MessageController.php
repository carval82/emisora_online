<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\StationSetting;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index()
    {
        $messages = Message::latest()->paginate(20);

        return view('admin.messages.index', compact('messages'));
    }

    public function markRead(Message $message)
    {
        $message->update(['is_read' => true]);

        return back()->with('success', 'Mensaje marcado como leído.');
    }

    public function toggleApproval(Message $message)
    {
        $message->update(['is_approved' => ! $message->is_approved]);

        return back()->with('success', 'Estado del mensaje actualizado.');
    }

    public function destroy(Message $message)
    {
        $message->delete();

        return back()->with('success', 'Mensaje eliminado.');
    }

    public function updateStation(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slogan' => 'nullable|string|max:255',
        ]);

        $station = StationSetting::current();
        $station->update([
            'name' => $validated['name'],
            'slogan' => $validated['slogan'] ?? null,
        ]);

        return back()->with('success', 'Configuración de la emisora actualizada.');
    }
}
