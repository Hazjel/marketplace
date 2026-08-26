<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_store()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $payload = [
            'name' => 'Toko Bagus',
            'phone' => '081299998888',
            'city' => 'Jakarta',
            'address' => 'Jl Sudirman',
            'postal_code' => '12345',
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/register-store', $payload);

        $response->assertStatus(201);
        // Username digenerate dari nama toko + suffix acak (mis. toko-bagus-Y84x8)
        $this->assertDatabaseHas('stores', ['name' => 'Toko Bagus']);

        // User should now have 'store' role
        $this->assertTrue($user->fresh()->hasRole('store'));
    }

    public function test_store_logo_rejects_a_non_image_extension(): void
    {
        // validateMimes() Laravel menebak tipe file lewat guessExtension(),
        // yang untuk UploadedFile ASLI membaca konten (finfo), bukan cuma
        // percaya nama file -- verifikasi langsung ke
        // vendor/laravel/framework/.../ValidatesAttributes::validateMimes().
        // UploadedFile::fake()->create() sebagai test double TIDAK
        // mereplikasi content-sniffing itu (getMimeType()-nya balikin
        // properti mimeTypeToReport yang di-declare eksplisit atau nebak
        // dari NAMA file, bukan baca isi) -- jadi yang bisa diuji lewat
        // fake() secara realistis cuma "ekstensi yang jelas-jelas bukan
        // gambar ditolak", bukan "file berkonten palsu dengan ekstensi
        // benar ditolak" (itu perlu upload sungguhan, di luar jangkauan
        // test double ini).
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin'); // 'store-create' cuma digrant ke admin
        $notAnImage = UploadedFile::fake()->create('logo.txt', 10);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/store', [
            'user_id' => $user->id,
            'name' => 'Toko Palsu',
            'logo' => $notAnImage,
            'about' => 'Deskripsi',
            'phone' => '081299998888',
            'address_id' => 1,
            'city' => 'Jakarta',
            'address' => 'Jl Sudirman',
            'postal_code' => '12345',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('logo');
    }
}
