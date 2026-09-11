# Money JSON contract

How monetary values appear in API responses, as of **Sprint C1**.

Companion to `money-contract.md` (the internal `Money` value object and
the B3.2 calculation migration). This document is about the **wire
format** only.

## Rule

Every field that holds a **whole-rupiah amount** is emitted as a JSON
**integer** — never a `decimal:2` string (`"150000.00"`) and never a
float. A JSON integer decodes natively as an int in any client language
(Dart, Kotlin, Swift, JS `Number`) — no string parsing needed.

```json
{ "price": 150000, "tax": 11006, "grand_total": 126056 }
```

The database columns are still `decimal(26,2)` (that migration is Sprint
C9); each resource below parses the stored value with
`Money::fromDecimalString(...)->minor()` on the way out — the same
checked boundary the domain uses everywhere else. A fractional legacy
value (a pre-B3.2c `discount_amount` could be `"10000.50"`) **throws**
rather than silently truncating to `10000` and misreporting financial
history.

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
| `ProductResource.weight` | number (2 dp) | kilograms, not money — `ProductVariantResource` has no `weight` field at all |
| `VoucherResource.value` when `type = "percentage"` | number (≤ 2 dp) | a **rate** (`10.5` = 10.5%), not a rupiah amount. Parsed internally to exact basis points, never narrowed. |
| `StoreBalanceResource.{balance, pending_balance}` | number (2 dp) | escrow ledger — a pre-B3.2d `pending_balance` can be fractional |
| `StoreBalanceHistoryResource.amount` | number (2 dp) | escrow ledger |
| `WithdrawalResource.amount` | number (2 dp) | escrow ledger |
| `SellerDashboardResource.{balance, pending_balance}` | number (2 dp) | reads the escrow ledger |
| `AdminDashboardResource.{total_revenue, total_admin_fee}` | `(float)` | raw `SUM(grand_total)` / `SUM(admin_fee)`, not narrowed |
| `GET /api/transaction/all/paginated` → `meta.{total_revenue, total_admin_fee}` | unnormalized | `TransactionController` puts `TransactionAnalyticsRepository::getTotalRevenue()/getTotalAdminFee()` straight into the pagination `meta` block — **no Resource in the path at all**, so the wire type is whatever the DB driver's `SUM()` returns (typically a numeric string) |
| `BuyerDashboardResource.total_expense` | `(float)` | same `SUM(grand_total)` path as `AdminDashboardResource` |

Not an exception, despite also being a `SUM`: the dashboard **chart**
series (`TransactionAnalyticsRepository::getChartData()` →
`chart[].total_revenue`) is already `(int)`-cast at the repository.

The escrow-ledger fields, and the un-normalized dashboard/`meta` sums
above, move to integers in **C9**, alongside the column migration — they
are out of scope for C1.

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

- Decode the integer money fields as a native int (Dart's `jsonDecode`
  already does this for a JSON integer — no `int.tryParse()` needed;
  that method is for parsing a `String`, and these fields are never one).
- For a percentage voucher, `value` is a percentage — apply as
  `amount * value / 100` for display, but the server is authoritative on
  the actual discount.
- The escrow-ledger fields (`balance`, `pending_balance`, `amount`) and
  the dashboard/`meta` sums listed above may carry decimals or arrive as
  a numeric string until C9 — parse them defensively (decimal/double,
  tolerate a string).
