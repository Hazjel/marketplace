<?php

namespace Tests\Feature;

use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\PaymentGatewayInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Buyer;
use App\Models\Complaint;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\StoreBalanceHistory;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Notifications\ComplaintNotification;
use App\Notifications\RefundStatusNotification;
use App\Services\PushNotificationService;
use App\Support\ComplaintAlerts;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    private User $sellerUser;

    private User $buyerUser;

    private Store $store;

    private StoreBalance $storeBalance;

    private Buyer $buyer;

    private Product $product;

    /** @var list<string> */
    public array $refundCalls = [];

    private int $seq = 0;

    /** @var list<array{string, string}> [user id, body] of every push */
    private array $pushes = [];

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        $this->app->instance(PaymentGatewayInterface::class, new class($this) implements PaymentGatewayInterface
        {
            public function __construct(private ComplaintTest $test) {}

            public function getSnapToken(Transaction $transaction): ?string
            {
                return null;
            }

            public function refund(Transaction $transaction, string $reason): string
            {
                $this->test->refundCalls[] = $transaction->code;

                return PaymentGatewayInterface::REFUND_DONE;
            }
        });

        $this->sellerUser = User::factory()->create();
        $this->sellerUser->assignRole('store');
        $this->store = Store::create([
            'user_id' => $this->sellerUser->id,
            'name' => 'Complaint Store',
            'username' => 'complaintstore',
            'logo' => 'default.png',
            'about' => 'Test',
            'phone' => '08123456789',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Test',
            'postal_code' => '12345',
            'is_verified' => true,
        ]);
        $this->storeBalance = StoreBalance::create(['store_id' => $this->store->id, 'balance' => 0, 'pending_balance' => 0]);

        $category = ProductCategory::create(['name' => 'Electronics', 'slug' => 'electronics', 'description' => 'Electronics']);
        $this->product = Product::create([
            'store_id' => $this->store->id,
            'product_category_id' => $category->id,
            'name' => 'Test Gadget',
            'slug' => 'test-gadget',
            'description' => 'A test gadget',
            'price' => 100000,
            'stock' => 48,
            'weight' => 0.5,
            'condition' => 'new',
        ]);

        [$this->buyerUser, $this->buyer] = $this->makeBuyer();
    }

    /** @return array{User, Buyer} */
    private function makeBuyer(): array
    {
        $user = User::factory()->create();
        $user->assignRole('buyer');

        return [$user, Buyer::create(['user_id' => $user->id, 'phone_number' => '08987654321'])];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    // Paid order with the seller's share held in escrow.
    private function order(string $deliveryStatus = 'delivering', int $balanceUsed = 0, string $paymentStatus = 'paid'): Transaction
    {
        $transaction = Transaction::create([
            'code' => 'BLKCMP'.(++$this->seq),
            'buyer_id' => $this->buyer->id,
            'store_id' => $this->store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 15000,
            'tax' => 0,
            'service_fee' => 1000,
            'grand_total' => 216000,
            'balance_used' => $balanceUsed,
            'payment_status' => $paymentStatus,
            'delivery_status' => $deliveryStatus,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'subtotal' => 200000,
        ]);

        if ($paymentStatus === 'paid') {
            app(EscrowRepositoryInterface::class)->credit($transaction);
        }

        return $transaction->fresh();
    }

    /** @param array<string, mixed> $extra */
    private function complain(User $user, Transaction $transaction, array $extra = [])
    {
        return $this->actingAs($user)->post("/api/transaction/{$transaction->id}/complaint", $extra + [
            'reason' => 'damaged',
            'description' => 'Layar retak saat paket dibuka',
        ], ['Accept' => 'application/json']);
    }

    private function openComplaint(?Transaction $transaction = null): Complaint
    {
        $transaction ??= $this->order();
        $this->complain($this->buyerUser, $transaction)->assertCreated();

        return Complaint::where('transaction_id', $transaction->id)->firstOrFail();
    }

    private function escalatedComplaint(): Complaint
    {
        $complaint = $this->openComplaint();
        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/reject", ['response' => 'Barang dikirim dalam kondisi baik'])->assertOk();

        return $complaint->fresh();
    }

    private function completeAsBuyer(Transaction $transaction)
    {
        return $this->actingAs($this->buyerUser)->post("/api/transaction/{$transaction->id}/complete", [
            'receiving_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], ['Accept' => 'application/json']);
    }

    private function assertRefundedWithoutRestock(Transaction $transaction): void
    {
        $transaction->refresh();
        $this->assertSame('cancelled', $transaction->delivery_status);
        $this->assertSame('failed', $transaction->payment_status);
        $this->assertSame('Komplain pembeli disetujui', $transaction->refund_reason);
        $this->assertEquals(0, (float) $this->storeBalance->fresh()->pending_balance);
        $this->assertEquals(48, $this->product->fresh()->stock);
        $this->assertSame(1, StoreBalanceHistory::where('reference_id', $transaction->id)->where('type', 'refunded')->count());
    }

    public function test_buyer_files_a_complaint_with_photos(): void
    {
        $transaction = $this->order();

        $response = $this->complain($this->buyerUser, $transaction, [
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
        ])->assertCreated()
            ->assertJsonPath('data.complaint.status', 'open')
            ->assertJsonPath('data.complaint.reason', 'damaged')
            ->assertJsonCount(2, 'data.complaint.photos');

        $complaint = Complaint::firstOrFail();
        $this->assertCount(2, $complaint->photos);
        foreach ($complaint->photos as $path) {
            Storage::disk('public')->assertExists($path);
            $this->assertContains(asset('storage/'.$path), $response->json('data.complaint.photos'));
        }
        $this->assertTrue($complaint->deadline_at->between(now()->addDays(2)->subMinute(), now()->addDays(2)->addMinute()));

        // Seller sees it on the order; escrow untouched.
        $this->actingAs($this->sellerUser)->getJson("/api/transaction/{$transaction->id}")
            ->assertOk()->assertJsonPath('data.complaint.status', 'open');
        $this->assertGreaterThan(0, (float) $this->storeBalance->fresh()->pending_balance);
    }

    public function test_complaint_needs_a_paid_delivering_order_without_one(): void
    {
        $this->complain($this->buyerUser, $this->order('processing'))->assertStatus(422);
        $this->complain($this->buyerUser, $this->order('completed'))->assertStatus(422);
        $this->complain($this->buyerUser, $this->order('delivering', 0, 'unpaid'))->assertStatus(422);

        $transaction = $this->order();
        $this->complain($this->buyerUser, $transaction)->assertCreated();
        $this->complain($this->buyerUser, $transaction)->assertStatus(422);
        $this->assertSame(1, Complaint::count());
    }

    public function test_only_the_orders_buyer_can_complain(): void
    {
        $transaction = $this->order();
        [$otherBuyer] = $this->makeBuyer();

        $this->complain($this->sellerUser, $transaction)->assertForbidden();
        $this->complain($otherBuyer, $transaction)->assertForbidden();
        $this->assertSame(0, Complaint::count());
    }

    public function test_complaint_input_is_validated(): void
    {
        $transaction = $this->order();
        $photo = fn () => UploadedFile::fake()->image('p.jpg');

        $this->complain($this->buyerUser, $transaction, ['photos' => [$photo(), $photo(), $photo(), $photo()]])->assertStatus(422);
        $this->complain($this->buyerUser, $transaction, ['photos' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]])->assertStatus(422);
        $this->complain($this->buyerUser, $transaction, ['photos' => [UploadedFile::fake()->image('big.jpg')->size(3000)]])->assertStatus(422);
        $this->complain($this->buyerUser, $transaction, ['reason' => 'changed_mind'])->assertStatus(422);
        $this->complain($this->buyerUser, $transaction, ['description' => 'pendek'])->assertStatus(422);
        $this->assertSame(0, Complaint::count());
    }

    public function test_buyer_withdraws_an_open_or_escalated_complaint(): void
    {
        $complaint = $this->openComplaint();

        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/withdraw")->assertForbidden();
        $this->actingAs($this->buyerUser)->postJson("/api/complaint/{$complaint->id}/withdraw")
            ->assertOk()->assertJsonPath('data.complaint.status', 'withdrawn');
        $this->actingAs($this->buyerUser)->postJson("/api/complaint/{$complaint->id}/withdraw")->assertStatus(422);

        $this->assertSame($this->buyerUser->id, $complaint->fresh()->resolved_by);
        $this->assertNotNull($complaint->fresh()->resolved_at);

        $escalated = $this->escalatedComplaint();
        $this->actingAs($this->buyerUser)->postJson("/api/complaint/{$escalated->id}/withdraw")
            ->assertOk()->assertJsonPath('data.complaint.status', 'withdrawn');
    }

    public function test_seller_accept_refunds_in_full_without_restocking(): void
    {
        $complaint = $this->openComplaint();
        $transaction = Transaction::findOrFail($complaint->transaction_id);
        $this->assertGreaterThan(0, (float) $this->storeBalance->fresh()->pending_balance);

        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.complaint.status', 'approved')
            ->assertJsonPath('data.refund_amount', 216000);

        $this->assertRefundedWithoutRestock($transaction);
        $this->assertSame([$transaction->code], $this->refundCalls);
        $this->assertSame('refunded', $transaction->refund_status);
        $this->assertSame('midtrans', $transaction->refund_method);
        $this->assertSame($this->sellerUser->id, $complaint->fresh()->resolved_by);
    }

    public function test_accept_returns_the_saldo_part_at_once(): void
    {
        $complaint = $this->openComplaint($this->order('delivering', 16000));

        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/accept")->assertOk();

        $transaction = Transaction::findOrFail($complaint->transaction_id);
        $this->assertRefundedWithoutRestock($transaction);
        $this->assertEquals(200000, (float) $transaction->refund_amount);
        $this->assertEquals(16000, (float) $this->buyer->fresh()->balance);
    }

    public function test_accept_of_an_order_paid_fully_with_saldo_is_refunded_to_saldo(): void
    {
        $complaint = $this->openComplaint($this->order('delivering', 216000));

        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/accept")->assertOk();

        $transaction = Transaction::findOrFail($complaint->transaction_id);
        $this->assertRefundedWithoutRestock($transaction);
        $this->assertSame('refunded', $transaction->refund_status);
        $this->assertSame('balance', $transaction->refund_method);
        $this->assertEquals(0, (float) $transaction->refund_amount);
        $this->assertEquals(216000, (float) $this->buyer->fresh()->balance);
        $this->assertSame([], $this->refundCalls);
    }

    public function test_seller_reject_escalates_to_admin(): void
    {
        $complaint = $this->openComplaint();
        [$otherBuyer] = $this->makeBuyer();

        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/reject", [])->assertStatus(422);
        $this->actingAs($this->buyerUser)->postJson("/api/complaint/{$complaint->id}/accept")->assertForbidden();
        $this->actingAs($otherBuyer)->postJson("/api/complaint/{$complaint->id}/reject", ['response' => 'Bukan urusan saya'])->assertForbidden();

        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/reject", ['response' => 'Barang dikirim utuh'])
            ->assertOk()
            ->assertJsonPath('data.complaint.status', 'escalated')
            ->assertJsonPath('data.complaint.seller_response', 'Barang dikirim utuh');
        $this->assertNotNull($complaint->fresh()->escalated_at);

        // Escalated: the seller no longer decides.
        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/accept")->assertStatus(422);
        $this->assertSame('delivering', Transaction::findOrFail($complaint->transaction_id)->delivery_status);
    }

    public function test_admin_approves_an_escalated_complaint(): void
    {
        $complaint = $this->escalatedComplaint();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/complaint/{$complaint->id}/resolve", ['outcome' => 'approve'])->assertStatus(422);
        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/resolve", ['outcome' => 'approve', 'note' => 'Setuju refund'])->assertForbidden();
        $this->actingAs($this->buyerUser)->postJson("/api/complaint/{$complaint->id}/resolve", ['outcome' => 'approve', 'note' => 'Setuju refund'])->assertForbidden();

        $this->actingAs($admin)->postJson("/api/complaint/{$complaint->id}/resolve", ['outcome' => 'approve', 'note' => 'Foto membuktikan rusak'])
            ->assertOk()
            ->assertJsonPath('data.complaint.status', 'approved')
            ->assertJsonPath('data.complaint.admin_note', 'Foto membuktikan rusak');

        $this->assertRefundedWithoutRestock(Transaction::findOrFail($complaint->transaction_id));
        $this->assertSame($admin->id, $complaint->fresh()->resolved_by);

        // Resolving again cannot refund twice.
        $this->actingAs($admin)->postJson("/api/complaint/{$complaint->id}/resolve", ['outcome' => 'approve', 'note' => 'Klik dua kali'])->assertStatus(422);
        $this->assertCount(1, $this->refundCalls);
    }

    public function test_admin_rejects_an_escalated_complaint_and_completion_works_again(): void
    {
        $complaint = $this->escalatedComplaint();
        $transaction = Transaction::findOrFail($complaint->transaction_id);

        $this->completeAsBuyer($transaction)->assertStatus(422)
            ->assertJsonPath('message', 'Tarik komplain terlebih dulu sebelum menyelesaikan pesanan');

        $this->actingAs($this->admin())->postJson("/api/complaint/{$complaint->id}/resolve", ['outcome' => 'reject', 'note' => 'Bukti tidak cukup'])
            ->assertOk()->assertJsonPath('data.complaint.status', 'rejected');
        $this->assertSame('delivering', $transaction->fresh()->delivery_status);
        $this->assertSame([], $this->refundCalls);

        $this->completeAsBuyer($transaction)->assertOk();
        $this->assertSame('completed', $transaction->fresh()->delivery_status);
    }

    public function test_open_complaint_blocks_completion_until_withdrawn(): void
    {
        $complaint = $this->openComplaint();
        $transaction = Transaction::findOrFail($complaint->transaction_id);

        $this->completeAsBuyer($transaction)->assertStatus(422);
        $this->assertSame('delivering', $transaction->fresh()->delivery_status);

        $this->actingAs($this->buyerUser)->postJson("/api/complaint/{$complaint->id}/withdraw")->assertOk();
        $this->completeAsBuyer($transaction)->assertOk();
    }

    public function test_seller_accept_twice_refunds_once(): void
    {
        $complaint = $this->openComplaint();

        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/accept")->assertOk();
        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/accept")->assertStatus(422);

        $this->assertRefundedWithoutRestock(Transaction::findOrFail($complaint->transaction_id));
        $this->assertCount(1, $this->refundCalls);
    }

    public function test_auto_complete_skips_orders_with_an_active_complaint(): void
    {
        $complaint = $this->openComplaint();
        $escalated = $this->escalatedComplaint();
        $plain = $this->order();
        DB::table('transactions')->update(['updated_at' => now()->subDays(8)->toDateTimeString()]);

        $this->artisan('transaction:auto-complete')->assertExitCode(0);

        $this->assertSame('delivering', Transaction::findOrFail($complaint->transaction_id)->delivery_status);
        $this->assertSame('delivering', Transaction::findOrFail($escalated->transaction_id)->delivery_status);
        $this->assertSame('completed', $plain->fresh()->delivery_status);

        // The repository refuses too, not just the command's query.
        $this->expectException(Exception::class);
        $this->expectExceptionCode(422);
        app(TransactionRepositoryInterface::class)->completeTransaction($complaint->transaction_id);
    }

    public function test_escalate_command_moves_only_expired_open_complaints_once(): void
    {
        $expired = $this->openComplaint();
        $fresh = $this->openComplaint();
        $answered = $this->escalatedComplaint();
        $expired->update(['deadline_at' => now()->subMinute()]);
        $answered->update(['deadline_at' => now()->subMinute()]);

        $this->artisan('complaints:escalate')->assertExitCode(0);

        $expired->refresh();
        $this->assertSame('escalated', $expired->status);
        $this->assertNull($expired->seller_response);
        $this->assertNotNull($expired->escalated_at);
        $this->assertSame('open', $fresh->fresh()->status);
        $this->assertSame('Barang dikirim dalam kondisi baik', $answered->fresh()->seller_response);

        $escalatedAt = $expired->escalated_at->toIso8601String();
        $this->travel(1)->hours();
        $this->artisan('complaints:escalate')->assertExitCode(0);
        $this->assertSame($escalatedAt, $expired->fresh()->escalated_at->toIso8601String());
    }

    public function test_seller_cannot_change_an_order_under_complaint(): void
    {
        $complaint = $this->openComplaint();
        $transaction = Transaction::findOrFail($complaint->transaction_id);

        $this->actingAs($this->sellerUser)->putJson("/api/transaction/{$transaction->id}", ['delivery_status' => 'processing'])
            ->assertStatus(422)->assertJsonPath('message', 'Pesanan sedang dikomplain');
        $this->assertSame('delivering', $transaction->fresh()->delivery_status);

        $this->actingAs($this->buyerUser)->postJson("/api/complaint/{$complaint->id}/withdraw")->assertOk();
        $this->actingAs($this->sellerUser)->putJson("/api/transaction/{$transaction->id}", ['delivery_status' => 'delivering', 'tracking_number' => 'JNE-CMP-1'])
            ->assertOk();
    }

    public function test_auto_complete_waits_two_days_after_a_late_withdrawal(): void
    {
        $complaint = $this->openComplaint();
        $transaction = Transaction::findOrFail($complaint->transaction_id);
        DB::table('transactions')->update(['updated_at' => now()->subDays(8)->toDateTimeString()]);
        $this->actingAs($this->buyerUser)->postJson("/api/complaint/{$complaint->id}/withdraw")->assertOk();

        $this->artisan('transaction:auto-complete')->assertExitCode(0);
        $this->assertSame('delivering', $transaction->fresh()->delivery_status);

        try {
            app(TransactionRepositoryInterface::class)->completeTransaction($transaction->id, null, true);
            $this->fail('auto path must wait 2 days after the complaint closed');
        } catch (Exception $e) {
            $this->assertSame(422, $e->getCode());
        }

        $this->travel(2)->days();
        $this->travel(1)->minutes();
        $this->artisan('transaction:auto-complete')->assertExitCode(0);
        $this->assertSame('completed', $transaction->fresh()->delivery_status);
    }

    private function logistics(string $awb, string $status)
    {
        config(['services.logistics.webhook_secret' => 'test-secret']);

        return $this->postJson('/api/logistics/webhook', ['awb' => $awb, 'status' => $status], ['X-Webhook-Secret' => 'test-secret']);
    }

    public function test_logistics_webhook_is_closed_without_a_configured_secret(): void
    {
        config(['services.logistics.webhook_secret' => '']);

        $this->postJson('/api/logistics/webhook', ['awb' => 'X1', 'status' => 'DELIVERED'], ['X-Webhook-Secret' => ''])
            ->assertForbidden();
    }

    public function test_logistics_webhook_rejects_a_wrong_secret(): void
    {
        config(['services.logistics.webhook_secret' => 'test-secret']);

        $this->postJson('/api/logistics/webhook', ['awb' => 'X1', 'status' => 'DELIVERED'], ['X-Webhook-Secret' => 'wrong'])
            ->assertForbidden();
    }

    public function test_logistics_delivered_never_completes_the_order(): void
    {
        $transaction = $this->order();
        $transaction->update(['tracking_number' => 'JNE-CMP-2']);
        $pending = (float) $this->storeBalance->fresh()->pending_balance;

        $this->logistics('JNE-CMP-2', 'DELIVERED')->assertOk()->assertJsonPath('data.new_status', 'delivering');

        $this->assertSame('delivering', $transaction->fresh()->delivery_status);
        $this->assertEquals($pending, (float) $this->storeBalance->fresh()->pending_balance);
        $this->complain($this->buyerUser, $transaction)->assertCreated();
    }

    public function test_logistics_only_moves_unshipped_paid_orders_to_delivering(): void
    {
        $processing = $this->order('processing');
        $processing->update(['tracking_number' => 'JNE-CMP-3']);
        $completed = $this->order('completed');
        $completed->update(['tracking_number' => 'JNE-CMP-4']);

        $this->logistics('JNE-CMP-3', 'ON_DELIVERY')->assertOk();
        $this->logistics('JNE-CMP-4', 'ON_DELIVERY')->assertOk();

        $this->assertSame('delivering', $processing->fresh()->delivery_status);
        $this->assertSame('completed', $completed->fresh()->delivery_status);
    }

    public function test_photos_are_deleted_when_the_complaint_is_refused(): void
    {
        $transaction = $this->order();
        $this->complain($this->buyerUser, $transaction)->assertCreated();

        $this->complain($this->buyerUser, $transaction, ['photos' => [UploadedFile::fake()->image('late.jpg')]])->assertStatus(422);

        $this->assertSame([], Storage::disk('public')->allFiles('assets/complaint'));
    }

    public function test_admin_lists_complaints_by_status(): void
    {
        $this->openComplaint();
        $escalated = $this->escalatedComplaint();

        $this->actingAs($this->sellerUser)->getJson('/api/complaint')->assertForbidden();
        $this->actingAs($this->buyerUser)->getJson('/api/complaint')->assertForbidden();

        $this->actingAs($this->admin())->getJson('/api/complaint')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $escalated->transaction_id)
            ->assertJsonPath('data.data.0.complaint.status', 'escalated');

        $this->actingAs($this->admin())->getJson('/api/complaint?status=open')
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.complaint.status', 'open');
    }

    private function watchMessages(): void
    {
        Notification::fake();
        $this->mock(PushNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendToUser')->andReturnUsing(function ($user, $title, $body) {
                $this->pushes[] = [$user->id, $body];
            });
        });
    }

    /**
     * Subject, lines, button and link of every complaint mail $user got, one string per mail.
     *
     * @return list<string>
     */
    private function complaintMails(User $user): array
    {
        return Notification::sent($user, ComplaintNotification::class)
            ->map(fn (ComplaintNotification $n) => implode("\n", [$n->subject, ...$n->lines, $n->actionText, $n->url]))
            ->values()->all();
    }

    /** @return list<string> */
    private function pushedTo(User $user): array
    {
        return array_values(array_map(fn ($p) => $p[1], array_filter($this->pushes, fn ($p) => $p[0] === $user->id)));
    }

    public function test_a_new_complaint_mails_and_pushes_the_seller(): void
    {
        $this->watchMessages();
        $complaint = $this->openComplaint();
        $transaction = Transaction::findOrFail($complaint->transaction_id);

        [$mail] = $this->complaintMails($this->sellerUser);
        $this->assertStringContainsString("Komplain baru untuk pesanan {$transaction->code}", $mail);
        $this->assertStringContainsString('Alasan: Barang rusak', $mail);
        $this->assertStringContainsString('Layar retak saat paket dibuka', $mail);
        $this->assertStringContainsString('Tanggapi sebelum '.$complaint->deadline_at->format('d/m/Y H:i').' WIB, setelah itu komplain diteruskan ke admin.', $mail);
        $this->assertStringEndsWith('/admin/transaction/'.$transaction->id, $mail);
        $this->assertSame(["Pembeli mengajukan komplain untuk pesanan {$transaction->code}."], $this->pushedTo($this->sellerUser));
        $this->assertSame([], $this->complaintMails($this->buyerUser));

        $html = (string) Notification::sent($this->sellerUser, ComplaintNotification::class)->first()->toMail($this->sellerUser)->render();
        $this->assertStringContainsString('Tanggapi Komplain', $html);
    }

    public function test_escalation_tells_the_buyer_whether_the_seller_rejected_or_stayed_silent(): void
    {
        $this->watchMessages();
        $this->escalatedComplaint();
        $silent = $this->openComplaint();
        $silent->update(['deadline_at' => now()->subMinute()]);

        $this->artisan('complaints:escalate')->assertExitCode(0);

        $mails = $this->complaintMails($this->buyerUser);
        $this->assertCount(2, $mails);
        $this->assertStringContainsString('sedang ditinjau admin', $mails[0]);
        $this->assertStringContainsString('Penjual menolak komplain: "Barang dikirim dalam kondisi baik"', $mails[0]);
        $this->assertStringContainsString('Penjual tidak menanggapi dalam 2 hari.', $mails[1]);
        $this->assertStringEndsWith('/'.$this->buyerUser->username.'/transaction/'.$silent->transaction_id, $mails[1]);
        $this->assertCount(2, $this->pushedTo($this->buyerUser));
        // The seller only heard about the two new complaints.
        $this->assertCount(2, $this->complaintMails($this->sellerUser));
    }

    public function test_admin_rejection_tells_buyer_and_seller_with_the_note(): void
    {
        $complaint = $this->escalatedComplaint();
        $this->watchMessages();

        $this->actingAs($this->admin())->postJson("/api/complaint/{$complaint->id}/resolve", ['outcome' => 'reject', 'note' => 'Bukti tidak cukup'])->assertOk();

        [$buyerMail] = $this->complaintMails($this->buyerUser);
        [$sellerMail] = $this->complaintMails($this->sellerUser);
        $this->assertStringContainsString('ditolak admin', $buyerMail);
        $this->assertStringContainsString('Catatan admin: Bukti tidak cukup', $buyerMail);
        $this->assertStringContainsString('ditolak admin, pesanan berlanjut', $sellerMail);
        $this->assertStringContainsString('Catatan admin: Bukti tidak cukup', $sellerMail);
        $this->assertCount(1, $this->pushedTo($this->buyerUser));
        $this->assertCount(1, $this->pushedTo($this->sellerUser));
    }

    public function test_withdrawal_tells_the_seller(): void
    {
        $complaint = $this->openComplaint();
        $this->watchMessages();

        $this->actingAs($this->buyerUser)->postJson("/api/complaint/{$complaint->id}/withdraw")->assertOk();

        [$mail] = $this->complaintMails($this->sellerUser);
        $this->assertStringContainsString('Pembeli menarik komplain', $mail);
        $this->assertSame([], $this->complaintMails($this->buyerUser));
    }

    public function test_admin_approval_tells_the_seller_and_leaves_the_buyer_to_the_refund_mail(): void
    {
        $complaint = $this->escalatedComplaint();
        $this->watchMessages();

        $this->actingAs($this->admin())->postJson("/api/complaint/{$complaint->id}/resolve", ['outcome' => 'approve', 'note' => 'Foto membuktikan rusak'])->assertOk();

        [$mail] = $this->complaintMails($this->sellerUser);
        $this->assertStringContainsString('disetujui admin. Dana dikembalikan penuh ke pembeli', $mail);
        $this->assertSame([], $this->complaintMails($this->buyerUser));
        Notification::assertSentTo($this->buyerUser, RefundStatusNotification::class);
    }

    public function test_seller_who_accepts_gets_no_mail_about_it(): void
    {
        $complaint = $this->openComplaint();
        $this->watchMessages();

        $this->actingAs($this->sellerUser)->postJson("/api/complaint/{$complaint->id}/accept")->assertOk();

        $this->assertSame([], $this->complaintMails($this->sellerUser));
        $this->assertSame([], $this->pushedTo($this->sellerUser));
    }

    public function test_a_complaint_status_is_announced_once(): void
    {
        $this->watchMessages();
        $complaint = $this->openComplaint();

        ComplaintAlerts::send(Transaction::findOrFail($complaint->transaction_id));

        $this->assertCount(1, $this->complaintMails($this->sellerUser));
        $this->assertCount(1, $this->pushedTo($this->sellerUser));
    }

    public function test_a_failing_mail_does_not_fail_the_complaint(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('smtp down'));
        Log::spy();

        $transaction = $this->order();
        $this->complain($this->buyerUser, $transaction)->assertCreated();

        $this->assertSame('open', Complaint::where('transaction_id', $transaction->id)->value('status'));
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'Notifikasi komplain gagal' && $context['error'] === 'smtp down');
    }
}
