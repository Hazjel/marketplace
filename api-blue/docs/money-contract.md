# Money contract (Sprint B3)

Status: **frozen 2026-09-10** (B3.1 primitive) / **B3.2 calculation
migration complete** — see §6. This document is the reference the money
primitive and every calculation that touches currency must conform to.

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

`percentage()` is overflow-safe with no float and no BCMath: **both**
the amount and the basis points are split into a 10_000-quotient and
remainder, so `amount * bp / 10_000` becomes
`aQ*bQ*10_000 + aQ*bR + aR*bQ + aR*bR/10_000`. The `aR*bR` term (`< 1e8`)
is the only rounded part and cannot overflow; each product in the whole
part is range-checked. If the true result fits in a PHP int it is
computed exactly no matter how large `basisPoints` is; only a result
that genuinely exceeds `PHP_INT_MAX` throws.

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

## 6. Calculation migration — B3.2 (done)

B3.1 delivered the primitive and one persistence proof. B3.2 migrated the
calculation layer onto it, one increment per commit, so a primitive bug
stayed distinguishable from a calculation bug. All six gaps below are
closed.

| # | Gap (as of B3.1) | Resolution | Commit |
|---|---|---|---|
| 1 | FE `ppnSelected` / `grandTotalSelected` unrounded; `grandTotalWithDelivery` rounded once at the end — two totals for one order could disagree. | `cart.js`: PPN rounded HALF_UP at the tax step, then exact add/subtract; `grandTotalWithDelivery` drops its trailing `Math.round`. Display-only — the server still recomputes. | B3.2e |
| 2 | `admin_fee` never rounded — `$netSales * 0.10` with no rounding. | `EscrowRepository::applyAdminFee` uses `Money->percentage(admin_fee_basis_points)` (HALF_UP), locked into `transactions.admin_fee`. | B3.2d |
| 3 | Two divergent seller-amount implementations (`TransactionService` recomputed from config; `EscrowRepository` locked at credit). | `App\Services\TransactionService` was dead code — deleted. `EscrowRepository` is the single source; `sellerAmount()` reads the locked fee and never recomputes. | B3.2d |
| 4 | Rates are float literals — `0.11`, `0.10`, `voucher.value / 100`. | Basis points everywhere: tax `1100`, admin fee `1000` (`config('marketplace.admin_fee_basis_points')`), voucher rate parsed exactly from the `decimal:2` string (`"10.50"` → `1050`). | B3.2b–d |
| 5 | Voucher discount `round($discount, 2)`, not integer. | `Voucher::validateFor(string, string, Money): array` — discount is `Money` via `percentage(bp)` / whole-rupiah fixed value, capped at the subtotal by Money comparison. | B3.2c |
| 6 | `products.price` / variant price not integer-constrained. | `ProductStoreRequest` + `ProductUpdateRequest`: `price` and `variants.*.price` rules `numeric` → `integer`. | B3.2a |

**Merge/deploy gates** (fork A — a value that won't parse as `Money`
throws at the boundary). These were run against production and each
returned **0** before B3.2 was deployed (2026-09-10):

1. **Admin-fee config** — `config('marketplace.admin_fee_percentage')` on
   the still-old production code returned `0.1`, confirming the effective
   rate is 10% and the new `admin_fee_basis_points` default of `1000`
   preserves it.
2. **Product / variant price** — `App\ValueObjects\Money::fromDecimalString()`
   over every `Product::price` and `ProductVariantMongo::price` — count of
   values that throw.
3. **Voucher rupiah fields** — the same parser over every `type = fixed`
   voucher `value`, and every `min_purchase` / `max_discount`.
4. **Percentage voucher range** — any `type = percentage` voucher with
   `value < 0` or `value > 92233720368547758.07` (the largest rate whose
   basis points fit a PHP int — `Voucher::parsePercentageBasisPoints()`).

**C1 gate (`money-json-contract.md`'s integer resources):** before C1
deploys, run `Money::fromDecimalString()` over every `Transaction` row's
`shipping_cost`, `tax`, `grand_total` and `discount_amount` — must return
**0** for all four. `shipping_cost`/`tax`/`grand_total` were always
`round()`-ed to whole even pre-B3.2 and so were never actually at risk;
`discount_amount` is the one field a pre-B3.2c percentage voucher could
have left fractional (`round($discount, 2)`), and is the reason
`TransactionResource` parses it with the checked boundary instead of a
raw `(int)` cast — a value that fails this gate throws in the resource
rather than silently truncating.

**Not migrated in B3.2** (deliberate, still scalar / `decimal:2`):

- `transactions.{tax, grand_total, shipping_cost, admin_fee, discount_amount}`
  columns keep the `decimal:2` cast; the calculations produce `Money` and
  persist `->minor()`. The API resources emit these as **integers**
  (Sprint C1) — see `money-json-contract.md`.
- `store_balances`, `store_balance_histories`, `withdrawals` — the escrow
  ledger runs on scalars, and their API fields stay decimal (a pre-B3.2d
  `pending_balance` can hold a fractional value).
- `EscrowRepository::sellerAmount()` pays the `seller_amount` locked at
  credit; only a row credited before that column existed falls back to
  `grand_total - shipping_cost - admin_fee`, without going through `Money`.
- `decimal` → `bigint` column migration — still deferred.

## 7. Order money model (2026-10)

Replaces the B3.2b model (11% "PPN" on every order, shipping kept by the
platform, fee on goods + tax). Older transactions keep the `tax` and
`seller_amount` they were created and credited with.

```
grand_total   = subtotal − discount + shipping_cost + service_fee   (tax = 0)
goods         = grand_total − shipping_cost − tax − service_fee
admin_fee     = goods × marketplace.admin_fee_basis_points  (HALF_UP)
seller_amount = goods − admin_fee + shipping_cost
platform      = admin_fee + service_fee   (pays the payment-gateway fee)
```

- No buyer tax: goods VAT belongs to PKP sellers inside their own price.
- Shipping goes to the seller, who books and pays the courier.
- Vouchers (store or platform) are funded by the seller; the discount never
  reduces `service_fee`, which is added after it.
- `service_fee` = `marketplace.buyer_service_fee` (Rp1.000, env
  `BUYER_SERVICE_FEE`), once per order; checkout creates one order per store.
- `admin_fee` and `seller_amount` are locked at escrow credit; release and
  refund never recompute.

### Seller cancels a paid order (refund)

`POST /transaction/{id}/cancel` (seller or admin; only `paid` and
`pending`/`processing`) restores stock, reverses the seller's
`pending_balance` (`refunded` ledger entry), sets the order to
`cancelled` / `payment_status = failed` (so revenue analytics skip it), and
credits `balance_used` back to Saldo Blukios at once (`refund_balance:{id}`)
and records `refund_amount = grand_total − balance_used` (the Midtrans part):
the buyer gets everything back, service fee and shipping included. The
platform absorbs the Midtrans fee. A `refund_amount` of 0 (paid fully with
Saldo Blukios) is `refunded` / `balance` immediately, without a job.

`refund_status` then tracks the buyer's money:

- `processing`: `RefundCancelledTransactionJob` calls Midtrans (card void
  before settlement, `/refund` after; card, GoPay, ShopeePay, QRIS, Akulaku).
  Midtrans answers 414 (insufficient funds) until the payment has settled
  into the merchant balance, so 414, 429, 5xx and network errors are retried
  for ~1.8 days; any other rejection is final.
- `manual_required`: bank VA and other methods Midtrans cannot refund, or an
  API refund that was rejected or kept failing (`refund_note` says why). The buyer submits
  a bank account (`/refund-account`, stored encrypted, hidden from the
  seller); an admin transfers and calls `/mark-refunded`.
- `refunded`: done (`refund_method` `midtrans` or `manual`). A Midtrans
  `refund` webhook also lands here.

### Buyer complaint on a shipped order (refund)

`POST /transaction/{id}/complaint` (buyer; only `paid` + `delivering`, one
per order). While the complaint is `open` or `escalated` the order cannot be
completed (buyer gets 422, `transaction:auto-complete` skips it), so escrow
stays held. The seller accepts (`approved`) or rejects with a response
(`escalated`); an unanswered complaint escalates after 2 days
(`complaints:escalate`). An admin approves or rejects an escalated one; the
buyer can withdraw it before that. `approved` is the same full refund as a
seller cancel (same code path, same transaction as the approval) except that
stock is not restored: the buyer keeps the goods.

### Paying with Saldo Blukios

`use_balance: true` on checkout allocates min(balance, `grand_total`) to the
orders in creation order (`balance_used`, ledger `payment:{id}`).
`grand_total` stays the full order value; escrow, `admin_fee` and
`seller_amount` are unchanged. Midtrans collects and refunds only
`Transaction::midtransAmount()` = `grand_total − balance_used` (Snap
`gross_amount`, webhook and manual-check amount checks, refunds). A payment
whose Midtrans part is 0 is marked paid inside the checkout transaction.
An unpaid order that fails, expires or is deleted returns `balance_used`
once (`payment_returned:{id}`); one order of a shared payment cannot be
deleted (422). If Snap cannot create the payment, checkout fails its orders
at once (502) instead of leaving them to expire.

`failed` is terminal. A settlement that still arrives (webhook or manual
check) never marks the order paid: its Midtrans part becomes a refund
(`refund_status = processing`, reason "Pembayaran masuk setelah pesanan
kedaluwarsa", same job as a seller cancel) and `ops:check` reports it
(`late_payment_refunded`, informational).

Lock order on every money path: transactions → products → seller wallets
(`store_balances`) → buyer (`buyers`, Saldo Blukios), always last.

## 8. API / naming

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
