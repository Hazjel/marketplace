# Money JSON contract

How monetary values appear in API responses, as of **Sprint C1**.

Companion to `money-contract.md` (the internal `Money` value object and
the B3.2 calculation migration). This document is about the **wire
format** only.

## Rule

Every field that holds a **whole-rupiah amount** is emitted as a JSON
**integer** — never a `decimal:2` string (`"150000.00"`) and never a
float. A client can `int.tryParse()` / `Integer(...)` it directly.

```json
{ "price": 150000, "tax": 11006, "grand_total": 126056 }
```

The database columns are still `decimal(26,2)` (that migration is Sprint
C9); the API layer casts them to `int` on the way out because the value
is always whole after B3.2.

## Integer fields

| Resource | Field(s) |
|---|---|
| `ProductResource` | `price` |
| `ProductVariantResource` | `price` |
| `TransactionResource` | `shipping_cost`, `tax`, `grand_total`, `discount_amount` |
| `TransactionDetailResource` | `subtotal` (B3.1 — `Money` cast, emits `->minor()`) |
| `VoucherResource` | `value` **when `type = "fixed"`**, `min_purchase`, `max_discount` |
| `VoucherController::validateCode` response | `discount_amount` |

## Exceptions — NOT integers

| Field | Type | Why |
|---|---|---|
| `ProductResource.weight`, `ProductVariantResource` weight | number (2 dp) | kilograms, not money |
| `VoucherResource.value` when `type = "percentage"` | number (≤ 2 dp) | a **rate** (`10.5` = 10.5%), not a rupiah amount. Parsed internally to basis points. |
| `StoreBalanceResource.{balance, pending_balance}` | number (2 dp) | escrow ledger — a pre-B3.2d `pending_balance` can be fractional |
| `StoreBalanceHistoryResource.amount` | number (2 dp) | escrow ledger |
| `WithdrawalResource.amount` | number (2 dp) | escrow ledger |
| `SellerDashboardResource.{balance, pending_balance}` | number (2 dp) | reads the escrow ledger |
| `BuyerDashboardResource.total_expense`, dashboard chart revenue | number | aggregate `SUM(grand_total)`; whole in practice but not yet contract-guaranteed |

The escrow-ledger and dashboard money fields move to integers in **C9**,
alongside the column migration.

## Request side

Write endpoints require whole rupiah where the value is money:

| Endpoint | Field | Rule |
|---|---|---|
| `POST/PUT /api/product` | `price`, `variants.*.price` | `integer` |
| `POST/PUT /api/my-store/vouchers` | `min_purchase`, `max_discount` | `integer` |
| `POST/PUT /api/my-store/vouchers` | `value` (`type = fixed`) | parses as whole-rupiah `Money` |
| `POST/PUT /api/my-store/vouchers` | `value` (`type = percentage`) | `≤ 2` decimal places, within basis-point range |
| `POST /api/voucher/validate` | `subtotal` | `integer` |

## Client guidance

- Parse the integer money fields as integers. Do not divide by 100.
- For a percentage voucher, `value` is a percentage — apply as
  `amount * value / 100` for display, but the server is authoritative on
  the actual discount.
- The escrow-ledger fields (`balance`, `pending_balance`, `amount`) may
  carry two decimals until C9 — parse them as decimals / doubles.
