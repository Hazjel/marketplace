<?php

namespace App\Interfaces;

interface ChatAssistantInterface
{
    /**
     * Minta balasan otomatis dari asisten AI toko. Return null kalau
     * gateway gagal/reply kosong -- caller tidak boleh kirim pesan palsu.
     *
     * @param  array<int, array{name: string, price: float, stock: int}>  $products
     */
    public function reply(string $storeName, array $products, string $buyerMessage): ?string;
}
