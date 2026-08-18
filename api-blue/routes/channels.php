<?php

use App\Models\Transaction;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('chat.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('transaction.{id}', function ($user, $id) {
    $transaction = Transaction::find($id);

    if (! $transaction) {
        return false;
    }

    // Security: Prevent IDOR — same ownership check as
    // TransactionController::show()/checkPaymentStatus(). User bisa dual-role
    // (buyer + store) -- akses sah kalau dia buyer ATAU seller dari transaksi
    // ini, bukan harus lolos kedua guard sekaligus.
    $isOwningBuyer = $user->hasRole('buyer') && $transaction->buyer_id === $user->buyer?->id;
    $isOwningStore = $user->hasRole('store') && $transaction->store_id === $user->store?->id;
    $isAdmin = $user->hasRole('admin');

    return $isOwningBuyer || $isOwningStore || $isAdmin;
});

Broadcast::channel('online', function ($user) {
    if (auth()->check()) {
        return ['id' => $user->id, 'name' => $user->name, 'avatar' => $user->profile_picture, 'last_seen_at' => $user->last_seen_at];
    }
});
