<?php

namespace App\Interfaces;

use App\Models\Message;

interface ChatRepositoryInterface
{
    /**
     * Semua contact (user yang pernah tukar pesan dengan $userId), dengan
     * unread_count dan last_message terpasang, diurut dari pesan terbaru.
     */
    public function getContacts(string $userId);

    /**
     * Riwayat pesan antara dua user, urut lama ke baru, sender/receiver eager loaded.
     */
    public function getMessages(string $userId, string $otherUserId);

    /**
     * Tandai semua pesan dari $otherUserId ke $userId sebagai sudah dibaca.
     */
    public function markAsRead(string $userId, string $otherUserId): void;

    public function createMessage(string $senderId, string $receiverId, string $message): Message;
}
