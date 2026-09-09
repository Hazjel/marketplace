<?php

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Bridges a decimal(26,2) column to a Money value object.
 *
 * The DB column stays decimal(26,2) (see docs/money-contract.md — no
 * schema migration in B3.1). On read, the stored "N.00" string becomes
 * Money; a stored fractional value throws rather than being rounded.
 *
 * The write boundary is deliberately strict:
 *
 *   Money  -> stored
 *   int    -> stored   (legacy bridge for call sites not yet migrated)
 *   null   -> null     (only valid on a nullable column)
 *
 * float and string are rejected — accepting them would re-admit the
 * imprecision this primitive exists to remove. The set() input type is
 * `mixed` on purpose: the runtime guard is the point.
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::fromDecimalString((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return (string) $value->minor();
        }

        if (is_int($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException(
            "Kolom Money '{$key}' hanya menerima Money atau int, dapat ".get_debug_type($value).'.'
        );
    }
}
