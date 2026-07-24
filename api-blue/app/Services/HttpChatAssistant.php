<?php

namespace App\Services;

use App\Interfaces\ChatAssistantInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpChatAssistant implements ChatAssistantInterface
{
    public function reply(string $storeName, array $products, string $buyerMessage): ?string
    {
        try {
            $response = Http::withHeaders([
                'X-Internal-Key' => config('services.internal.key'),
            ])
                ->timeout(110)
                ->post(config('services.chat_service.url').'/store-assistant/reply', [
                    'store_name' => $storeName,
                    'products' => $products,
                    'buyer_message' => $buyerMessage,
                ]);

            if (! $response->successful()) {
                Log::warning('HttpChatAssistant: chat-service gagal', [
                    'store_name' => $storeName,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json('reply');
        } catch (\Throwable $e) {
            Log::error('HttpChatAssistant error: '.$e->getMessage(), [
                'store_name' => $storeName,
            ]);

            return null;
        }
    }
}
