<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Panggil chat-service (Ollama) buat generate balasan otomatis toko yang
 * mengaktifkan "Asisten AI", lalu simpan sebagai Message biasa (sender_id =
 * user pemilik toko) supaya tampil di UI chat existing tanpa perubahan FE besar.
 * Dijalankan async (bukan sinkron di ChatController::sendMessage) karena
 * panggilan ke Ollama bisa makan beberapa detik -- jangan sampai nge-block
 * response buyer yang baru saja kirim pesan.
 */
class GenerateAiChatReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 5;

    // Inference Ollama (qwen3:1.7b, CPU-only) bisa makan puluhan detik --
    // job timeout & HTTP client timeout harus lebih longgar dari itu.
    public int $timeout = 120;

    public function __construct(
        private readonly string $storeId,
        private readonly string $buyerId,
        private readonly string $buyerMessage
    ) {}

    public function handle(): void
    {
        $store = Store::with('user')->find($this->storeId);

        if (! $store || ! $store->ai_assistant_enabled || ! $store->user) {
            return;
        }

        $products = Product::where('store_id', $this->storeId)
            ->where('stock', '>', 0)
            ->latest()
            ->limit(30)
            ->get(['name', 'price', 'stock'])
            ->map(fn ($p) => [
                'name' => $p->name,
                'price' => (float) $p->price,
                'stock' => $p->stock,
            ])
            ->values()
            ->all();

        try {
            $response = Http::withHeaders([
                'X-Internal-Key' => config('services.internal.key'),
            ])
                ->timeout(110)
                ->post(config('services.chat_service.url').'/store-assistant/reply', [
                    'store_name' => $store->name,
                    'products' => $products,
                    'buyer_message' => $this->buyerMessage,
                ]);

            if (! $response->successful()) {
                Log::warning('GenerateAiChatReplyJob: chat-service gagal', [
                    'store_id' => $this->storeId,
                    'status' => $response->status(),
                ]);

                return;
            }

            $reply = $response->json('reply');

            if (! $reply) {
                Log::warning('GenerateAiChatReplyJob: reply kosong dari chat-service', [
                    'store_id' => $this->storeId,
                ]);

                return;
            }

            $message = Message::create([
                'sender_id' => $store->user->id,
                'receiver_id' => $this->buyerId,
                'message' => $reply,
                'is_ai_reply' => true,
            ]);

            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            // Gagal senyap -- jangan kirim pesan generik palsu, biarkan
            // seller yang balas manual kalau AI assistant lagi bermasalah.
            Log::error('GenerateAiChatReplyJob error: '.$e->getMessage(), [
                'store_id' => $this->storeId,
            ]);
        }
    }
}
