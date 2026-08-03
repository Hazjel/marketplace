<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        // userA = pembeli, userB = pemilik toko. Chat sekarang mensyaratkan
        // minimal satu pihak punya toko, jadi pasangan buyer<->buyer polos
        // tidak lagi merepresentasikan percakapan yang sah.
        $this->userA = User::factory()->create();
        $this->userA->assignRole('buyer');
        $this->userA->refresh();

        $this->userB = User::factory()->create();
        $this->userB->assignRole('store');
        Store::factory()->create(['user_id' => $this->userB->id]);
        $this->userB->refresh();
    }

    public function test_send_message_creates_and_returns_message(): void
    {
        $response = $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/chat/send', [
                'receiver_id' => $this->userB->id,
                'message' => 'Halo, ada stok?',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->userA->id,
            'receiver_id' => $this->userB->id,
            'message' => 'Halo, ada stok?',
        ]);
    }

    public function test_send_message_rejected_when_neither_party_owns_a_store(): void
    {
        $otherBuyer = User::factory()->create();
        $otherBuyer->assignRole('buyer');

        $response = $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/chat/send', [
                'receiver_id' => $otherBuyer->id,
                'message' => 'Halo sesama pembeli',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('messages', [
            'sender_id' => $this->userA->id,
            'receiver_id' => $otherBuyer->id,
        ]);
    }

    public function test_store_owner_can_message_another_store_owner_as_a_buyer(): void
    {
        // Seorang penjual tetap boleh belanja di toko lain, dan di percakapan
        // itu dia berperan sebagai pembeli. Aturan "role harus beda" akan
        // salah memblokir kasus ini karena keduanya menyandang role 'store'.
        $otherOwner = User::factory()->create();
        $otherOwner->assignRole('store');
        Store::factory()->create(['user_id' => $otherOwner->id]);

        $response = $this->actingAs($this->userB, 'sanctum')
            ->postJson('/api/chat/send', [
                'receiver_id' => $otherOwner->id,
                'message' => 'Saya mau beli produk Anda',
            ]);

        $response->assertStatus(201);
    }

    public function test_send_message_to_self_is_rejected(): void
    {
        $response = $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/chat/send', [
                'receiver_id' => $this->userA->id,
                'message' => 'Halo diri sendiri',
            ]);

        $response->assertStatus(422);
    }

    public function test_get_contacts_returns_unread_count_and_last_message(): void
    {
        Message::create([
            'sender_id' => $this->userB->id,
            'receiver_id' => $this->userA->id,
            'message' => 'Pesan pertama',
            'is_read' => true,
        ]);

        Message::create([
            'sender_id' => $this->userB->id,
            'receiver_id' => $this->userA->id,
            'message' => 'Pesan kedua (belum dibaca)',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/chat/contacts');

        $response->assertStatus(200);

        $contacts = $response->json('data');
        $this->assertCount(1, $contacts);
        $this->assertEquals($this->userB->id, $contacts[0]['id']);
        $this->assertEquals(1, $contacts[0]['unread_count']);
        $this->assertEquals('Pesan kedua (belum dibaca)', $contacts[0]['last_message']['message']);
    }

    public function test_get_messages_marks_unread_as_read(): void
    {
        $message = Message::create([
            'sender_id' => $this->userB->id,
            'receiver_id' => $this->userA->id,
            'message' => 'Halo',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/chat/'.$this->userB->id);

        $response->assertStatus(200);
        $this->assertTrue($message->fresh()->is_read);
    }

    public function test_get_messages_does_not_mark_own_sent_messages_as_read(): void
    {
        $ownMessage = Message::create([
            'sender_id' => $this->userA->id,
            'receiver_id' => $this->userB->id,
            'message' => 'Pesan saya sendiri',
            'is_read' => false,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/chat/'.$this->userB->id);

        $this->assertFalse($ownMessage->fresh()->is_read);
    }
}
