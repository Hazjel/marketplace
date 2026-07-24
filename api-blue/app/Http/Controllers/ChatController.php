<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Helpers\ResponseHelper;
use App\Interfaces\ChatRepositoryInterface;
use App\Jobs\GenerateAiChatReplyJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository
    ) {}

    public function getUserInfo($id)
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return ResponseHelper::jsonResponse(false, 'User not found', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'User info fetched successfully', [
                'id' => $user->id,
                'name' => $user->name,
                'profile_picture' => $user->profile_picture ? asset('storage/'.$user->profile_picture) : null,
                'last_seen_at' => $user->last_seen_at,
            ], 200);

        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getContacts()
    {
        try {
            $contacts = $this->chatRepository->getContacts(Auth::id());

            return ResponseHelper::jsonResponse(true, 'Contacts fetched successfully', $contacts, 200);

        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getMessages($otherUserId)
    {
        try {
            $userId = Auth::id();

            $this->chatRepository->markAsRead($userId, $otherUserId);
            $messages = $this->chatRepository->getMessages($userId, $otherUserId);

            return ResponseHelper::jsonResponse(true, 'Messages fetched successfully', $messages, 200);

        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);

        try {
            $message = $this->chatRepository->createMessage(Auth::id(), $request->receiver_id, $request->message);

            // Broadcast event
            broadcast(new MessageSent($message))->toOthers();

            // Kalau penerima adalah toko yang mengaktifkan Asisten AI, generate
            // balasan otomatis async (job) -- jangan sinkron di sini karena
            // panggilan ke Ollama bisa makan beberapa detik.
            $receiver = User::with('store')->find($request->receiver_id);
            if ($receiver?->store?->ai_assistant_enabled) {
                GenerateAiChatReplyJob::dispatch($receiver->store->id, Auth::id(), $request->message);
            }

            return ResponseHelper::jsonResponse(true, 'Message sent successfully', $message->load('sender'), 201);

        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
