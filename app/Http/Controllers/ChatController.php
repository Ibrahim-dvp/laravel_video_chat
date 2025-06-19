<?php

namespace App\Http\Controllers;

use App\Events\NewChatMessage;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function index()
    {
        $users = User::where('id', '!=', Auth::id())->get();
        return Inertia::render('Chat', [
            'users' => $users,
        ]);
    }

    public function messages(User $user)
    {
        $messages = ChatMessage::where(function ($q) use ($user) {
            $q->where('sender_id', Auth::id())
                ->where('receiver_id', $user->id);
        })->orWhere(function ($q) use ($user) {
            $q->where('sender_id', $user->id)
                ->where('receiver_id', Auth::id());
        })->orderBy('created_at')->get();

        return response()->json($messages);
    }

    public function sendMessage(Request $request, User $user)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $message = ChatMessage::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $user->id,
            'content' => $request->input('message'),
        ]);

        broadcast(new NewChatMessage($message))->toOthers();

        return response()->json($message);
    }
}
