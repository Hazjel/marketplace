<?php

namespace App\Repositories;

use App\Interfaces\ChatRepositoryInterface;
use App\Models\Message;
use App\Models\User;

class ChatRepository implements ChatRepositoryInterface
{
    public function getContacts(string $userId)
    {
        // Satu query ambil semua pesan yang melibatkan user ini, lalu hitung
        // unread_count & last_message per contact di memori -- menghindari
        // 2 query per contact (N+1) yang sebelumnya jalan di foreach.
        // Tie-break pakai id (UUIDv7, time-ordered) -- created_at cuma presisi
        // detik, jadi 2 pesan di detik yang sama butuh urutan sekunder yang stabil.
        $messages = Message::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'sender_id', 'receiver_id', 'message', 'is_read', 'created_at']);

        $contactIds = $messages
            ->map(fn ($m) => $m->sender_id === $userId ? $m->receiver_id : $m->sender_id)
            ->unique()
            ->values();

        $contacts = User::whereIn('id', $contactIds)->get()->keyBy('id');

        foreach ($contactIds as $contactId) {
            $contact = $contacts->get($contactId);
            if (! $contact) {
                continue;
            }

            $conversation = $messages->filter(
                fn ($m) => ($m->sender_id === $userId && $m->receiver_id === $contactId)
                    || ($m->sender_id === $contactId && $m->receiver_id === $userId)
            );

            $contact->unread_count = $conversation
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            $contact->last_message = $conversation->first();
        }

        return $contacts->values()
            ->sortByDesc(fn ($contact) => $contact->last_message?->created_at ?? $contact->created_at)
            ->values();
    }

    public function getMessages(string $userId, string $otherUserId)
    {
        return Message::where(function ($q) use ($userId, $otherUserId) {
            $q->where('sender_id', $userId)->where('receiver_id', $otherUserId);
        })->orWhere(function ($q) use ($userId, $otherUserId) {
            $q->where('sender_id', $otherUserId)->where('receiver_id', $userId);
        })
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function markAsRead(string $userId, string $otherUserId): void
    {
        Message::where('sender_id', $otherUserId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function createMessage(string $senderId, string $receiverId, string $message): Message
    {
        return Message::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $message,
        ]);
    }
}
