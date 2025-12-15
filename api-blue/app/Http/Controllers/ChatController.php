<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Helpers\ResponseHelper;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function getUserInfo($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return ResponseHelper::jsonResponse(false, 'User not found', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'User info fetched successfully', $user, 200);

        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getContacts()
    {
        try {
            $userId = Auth::id();

            // Get IDs of users who sent messages to current user
            $senders = Message::where('receiver_id', $userId)
                ->pluck('sender_id');

            // Get IDs of users who received messages from current user
            $receivers = Message::where('sender_id', $userId)
                ->pluck('receiver_id');

            // Merge and unique
            $contactIds = $senders->merge($receivers)->unique()->values();

            $contacts = User::whereIn('id', $contactIds)->get();

            // Optional: Attach last message for preview?
            // For now just return users
            return ResponseHelper::jsonResponse(true, 'Contacts fetched successfully', $contacts, 200);

        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getMessages($otherUserId)
    {
        try {
            $userId = Auth::id();

            $messages = Message::where(function($q) use ($userId, $otherUserId) {
                $q->where('sender_id', $userId)
                  ->where('receiver_id', $otherUserId);
            })->orWhere(function($q) use ($userId, $otherUserId) {
                $q->where('sender_id', $otherUserId)
                  ->where('receiver_id', $userId);
            })
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'asc')
            ->get();

            return ResponseHelper::jsonResponse(true, 'Messages fetched successfully', $messages, 200);

        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string'
        ]);

        try {
            $message = Message::create([
                'sender_id' => Auth::id(),
                'receiver_id' => $request->receiver_id,
                'message' => $request->message
            ]);

            // Broadcast event
            broadcast(new MessageSent($message))->toOthers();

            return ResponseHelper::jsonResponse(true, 'Message sent successfully', $message->load('sender'), 201);

        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
