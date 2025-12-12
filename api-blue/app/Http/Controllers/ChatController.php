<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function chat(Request $request)
    {
        // 1. Validasi pesan tidak boleh kosong
        $request->validate([
            'message' => 'required|string'
        ]);

        try {
            // 2. Tembak ke Server Python (Port 8001)
            // Pastikan URL-nya sama persis dengan yang ada di main.py
            $response = Http::post('http://127.0.0.1:8001/predict', [
                'message' => $request->message
            ]);

            // 3. Ambil jawaban dari JSON Python
            $botReply = $response->json()['reply'] ?? 'Maaf, Ri sedang gangguan.';

            // 4. Kembalikan ke Vue
            return ResponseHelper::success([
                'reply' => $botReply
            ]);

        } catch (\Exception $e) {
            return ResponseHelper::error(null, 'Gagal menghubungi AI Service: ' . $e->getMessage(), 500);
        }
    }
}
