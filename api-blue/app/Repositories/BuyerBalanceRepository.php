<?php

namespace App\Repositories;

use App\Interfaces\BuyerBalanceRepositoryInterface;
use App\Models\Buyer;
use App\Models\BuyerBalanceHistory;
use App\ValueObjects\Money;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class BuyerBalanceRepository implements BuyerBalanceRepositoryInterface
{
    public function credit(string $buyerId, string $amount, string $type, string $uniqueRef, ?Model $reference = null, ?string $remarks = null): bool
    {
        return $this->apply($buyerId, $this->positive($amount), $type, $uniqueRef, $reference, $remarks);
    }

    public function debit(string $buyerId, string $amount, string $type, string $uniqueRef, ?Model $reference = null, ?string $remarks = null): bool
    {
        return $this->apply($buyerId, Money::zero()->subtract($this->positive($amount)), $type, $uniqueRef, $reference, $remarks);
    }

    public function lockedBalance(string $buyerId): string
    {
        $buyer = Buyer::where('id', $buyerId)->lock('for no key update')->first();
        if (! $buyer) {
            throw new Exception('Pembeli tidak ditemukan', 404);
        }

        return (string) $buyer->balance;
    }

    private function apply(string $buyerId, Money $delta, string $type, string $uniqueRef, ?Model $reference, ?string $remarks): bool
    {
        if (! in_array($type, BuyerBalanceHistory::TYPES, true)) {
            throw new InvalidArgumentException("Tipe mutasi Saldo Blukios tidak dikenal: '{$type}'.");
        }

        return DB::transaction(function () use ($buyerId, $delta, $type, $uniqueRef, $reference, $remarks) {
            // Serializes this buyer's mutations so the checks below cannot race.
            // NO KEY UPDATE, not FOR UPDATE: the FK inserts (transactions,
            // histories) take KEY SHARE on this row, which FOR UPDATE would block.
            $buyer = Buyer::where('id', $buyerId)->lock('for no key update')->first();
            if (! $buyer) {
                throw new Exception('Pembeli tidak ditemukan', 404);
            }

            // Checked up front: a unique violation would abort the caller's
            // Postgres transaction, not just this savepoint.
            $existing = BuyerBalanceHistory::where('unique_ref', $uniqueRef)->first();
            if ($existing) {
                if ($existing->buyer_id === $buyer->id
                    && $existing->type === $type
                    && Money::fromDecimalString((string) $existing->amount)->equals($delta)) {
                    return false;
                }

                throw new LogicException("unique_ref '{$uniqueRef}' sudah dipakai untuk mutasi Saldo Blukios lain.");
            }

            $balance = Money::fromDecimalString((string) $buyer->balance)->add($delta);
            if ($balance->isNegative()) {
                throw new Exception('Saldo Blukios tidak mencukupi', 422);
            }

            $buyer->balance = (string) $balance->minor();
            $buyer->save();

            BuyerBalanceHistory::create([
                'buyer_id' => $buyer->id,
                'type' => $type,
                'amount' => $delta->minor(),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'unique_ref' => $uniqueRef,
                'remarks' => $remarks,
            ]);

            return true;
        });
    }

    private function positive(string $amount): Money
    {
        try {
            $money = Money::fromDecimalString($amount);
        } catch (\Throwable) {
            throw new Exception('Nominal Saldo Blukios tidak sah', 422);
        }

        if (! $money->greaterThan(Money::zero())) {
            throw new Exception('Nominal Saldo Blukios harus lebih dari 0', 422);
        }

        return $money;
    }
}
