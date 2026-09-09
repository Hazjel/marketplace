# Money contract (Sprint B3.1)

Status: **frozen 2026-09-10**. This document is the reference the money
primitive and every later calculation-migration ticket must conform to.

## 1. Canonical representation

| Aspect        | Decision                                                        |
|---------------|----------------------------------------------------------------|
| Unit          | 1 `Money` unit = Rp 1 (**scale 0**, whole rupiah)              |
| Currency      | `IDR`, fixed                                                   |
| Storage type  | `int` (`private readonly int $minor`)                          |
| Range         | Amounts representable as a PHP 64-bit int (`|amount| <= PHP_INT_MAX`) |

Indonesian Rupiah has no sub-rupiah unit in circulation and every money
column in this codebase stores whole values, so there is no "cents"
layer. `Money` is **not** advertised as multi-currency.

`Money` does **not** cover the full range of the `decimal(26,2)` columns
it is persisted in — `decimal(26,2)` can hold values far above
`PHP_INT_MAX`. Any operation that would overflow the int range **throws**
(`RangeException`); it never silently degrades to float.

```
DB      "10500000.00"   (decimal(26,2) — storage compatibility layer)
Domain  Money(10500000)
API     10500000         (integer JSON)
```

## 2. Arithmetic contract

| Operation           | Rule                                                      |
|---------------------|----------------------------------------------------------|
| `add`               | exact; overflow → throw                                   |
| `subtract`          | exact; overflow → throw                                   |
| `multiplyByQty(int)`| exact; `qty >= 1` required; overflow → throw              |
| `percentage(bp)`    | `bp / 10_000` of the amount, **HALF_UP**; overflow-safe   |
| `clampMin(Money)`   | exact; returns the larger of `$this` and the floor        |

`percentage()` is the **only** method that rounds. Addition, subtraction
and quantity multiplication are always exact — a non-integer intermediate
is impossible at scale 0 and a range overflow is an error, not a rounding
opportunity.

### Rounding mode

`App\Enums\RoundingMode::HALF_UP` — ties away from zero, matching PHP's
`PHP_ROUND_HALF_UP`:

```
 150 bp=100 (1%)  ->  1.5   ->  2
 149 bp=100       ->  1.49  ->  1
-150 bp=100       -> -1.5   -> -2
-149 bp=100       -> -1.49  -> -1
```

Production percentage bases are non-negative today; the negative cases
are specified so the primitive is deterministic regardless.

### Percentages are basis points

Rates enter as integer basis points: `11%` → `1100`, `10%` → `1000`,
`0.01%` → `1`. A `decimal(5,2)` percent column ("11.00", "10.50") must be
converted to basis points by **exact string parsing**, never
`(int) round($value * 100)` (that re-introduces float).

## 3. `fromDecimalString()`

Two stages.

**Syntax** — must match `/^-?\d+(?:\.\d{1,2})?$/`:

```
valid:   "10500000"  "10500000.0"  "10500000.00"  "-10500000"  "-10500000.00"
invalid: ""  "1e5"  "+100"  "10,500"  " 100 "  ".50"  "100."  "abc"
```

**Whole-rupiah invariant** — after valid syntax, the fractional part
must be all zeros:

```
"10500000.00" ok      "10.5"  reject (fractional rupiah)
"10500000.0"  ok      "10.50" reject
                       "10.01" reject
                       "-10.50" reject
```

A fractional value is a **domain violation** and throws — it is never
rounded away.

No float at any step: no `is_numeric()`, no `(float)`, no `round()`.

## 4. Persistence boundary

`App\Casts\MoneyCast` bridges a `decimal(26,2)` column to `Money`.

| Direction | Accepts                                                     |
|-----------|-----------------------------------------------------------|
| `get`     | `"N.00"` string → `Money`; stored fractional → throw       |
| `get`     | `null` → `null` (nullable columns only)                    |
| `set`     | `Money` → stored                                           |
| `set`     | `int` → stored (legacy bridge for un-migrated call sites)  |
| `set`     | `null` → `null` (nullable columns only)                    |
| `set`     | `float`, `string` → **throw**                              |

The DB column stays `decimal(26,2)` — **no schema migration in B3.1**.
A `decimal` → `bigint` migration is a separate, explicitly deferred
decision.

## 5. B3.1 pilot

`transaction_details.subtotal` only. Adaptation points:

- `TransactionDetailRepository` writer — builds `Money` from the resolved
  unit price and `multiplyByQty(qty)`.
- `TransactionDetailFactory` — same.
- `ProductFactory.price` — changed from `randomFloat(2)` to an integer
  generator (fractional factory prices violate the contract and would
  break the pilot writer). Test-only.
- `TransactionRepository` checkout reader — `$item->subtotal->minor()`;
  the rest of the checkout calculation is left on scalars for B3.2.
- `TransactionDetailResource` — emits `->minor()` (integer JSON instead
  of float). API value unchanged, type narrowed.

No frontend changes.

## 6. Known calculation gaps — recorded here, fixed in B3.2+

These are **not** addressed by B3.1. B3.1 delivers the primitive and one
persistence proof; the migrations below consume it afterward, so a
primitive bug stays distinguishable from a calculation-migration bug.

1. **FE `ppnSelected` / `grandTotalSelected` unrounded** (`cart.js`) —
   the display getters don't round; only `grandTotalWithDelivery()`
   does. Two totals for one order can disagree by a rupiah.
2. **`admin_fee` never rounded** — `TransactionService::calculateSellerAmount`
   and `EscrowRepository::applyAdminFee` both do `$netSales * 0.10`
   with no rounding.
3. **Two divergent seller-amount implementations** —
   `TransactionService::calculateSellerAmount` recomputes the fee from
   config on every call; `EscrowRepository` locks it at credit time and
   reuses it. They disagree if the config changes between events.
4. **Rates are float literals, not basis points** — `0.11`, `0.10`,
   `voucher.value / 100`.
5. **Voucher discount rounded to 2 dp, not integer** —
   `Voucher::validateFor` does `round($discount, 2)`.
6. **`products.price` / variant price not integer-constrained** —
   `ProductStoreRequest` allows `numeric`, so `X.50` prices are
   accepted and stored. Under B3.1 fork **A**, such a product becomes
   un-checkoutable (the pilot writer throws). A read-only production
   check (`price != FLOOR(price)` on `products` and product variants)
   must return **0** before the pilot is merged/deployed; a `price`
   integer validation rule + any needed backfill is the first B3.2 item.

## 7. API / naming

- Value object: `App\ValueObjects\Money` (immutable domain VO, not a
  helper).
- Enum: `App\Enums\RoundingMode`.
- Formatting (`"Rp 10.500.000"`) is **not** on the VO — presenters /
  resources / the FE `formatRupiah()` own it. The VO has no locale
  coupling.
- No `__toString()` — `Money` must not slip into a string context
  silently. Callers use `->minor()` explicitly.
- Deferred until a real consumer exists: `fromMinor()`, `toInt()`,
  `allocate()` (largest-remainder split — its tie-breaker is itself a
  contract question, answered when multi-seller allocation lands).
