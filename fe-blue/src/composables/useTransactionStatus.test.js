import { describe, expect, it } from 'vitest'
import {
  resolveRefundStatus,
  resolveShipDeadline,
  resolveTransactionStatus
} from './useTransactionStatus'

describe('resolveShipDeadline', () => {
  const now = Date.parse('2026-11-02T09:00:00Z')
  const order = (overrides) => ({
    payment_status: 'paid',
    delivery_status: 'pending',
    ship_deadline_at: '2026-11-05T09:00:00Z',
    ...overrides
  })

  it('only applies to paid orders that are not shipped yet', () => {
    expect(resolveShipDeadline(order({ delivery_status: 'processing' }), now)).not.toBeNull()
    expect(resolveShipDeadline(order({ delivery_status: 'delivering' }), now)).toBeNull()
    expect(resolveShipDeadline(order({ payment_status: 'unpaid' }), now)).toBeNull()
    expect(resolveShipDeadline(order({ ship_deadline_at: null }), now)).toBeNull()
  })

  it('counts down and turns urgent in the last day', () => {
    expect(resolveShipDeadline(order(), now)).toMatchObject({
      remaining: 'Sisa 3 hari 0 jam.',
      urgent: false
    })
    expect(
      resolveShipDeadline(order({ ship_deadline_at: '2026-11-02T14:30:00Z' }), now)
    ).toMatchObject({
      remaining: 'Sisa 5 jam.',
      urgent: true
    })
    expect(
      resolveShipDeadline(order({ ship_deadline_at: '2026-11-02T09:30:00Z' }), now).remaining
    ).toBe('Sisa kurang dari 1 jam.')
    expect(
      resolveShipDeadline(order({ ship_deadline_at: '2026-11-02T08:00:00Z' }), now).remaining
    ).toBe('Batas waktu lewat, pesanan segera dibatalkan otomatis.')
  })
})

describe('resolveRefundStatus', () => {
  it('is null when no refund is owed', () => {
    expect(resolveRefundStatus({ refund_status: null })).toBeNull()
    expect(resolveRefundStatus(undefined)).toBeNull()
  })

  it('labels each refund state', () => {
    expect(resolveRefundStatus({ refund_status: 'processing' }).label).toBe('Refund Diproses')
    expect(resolveRefundStatus({ refund_status: 'manual_required' }).label).toBe('Menunggu Refund')
    expect(resolveRefundStatus({ refund_status: 'refunded' }).label).toBe('Dana Dikembalikan')
  })
})

describe('resolveTransactionStatus', () => {
  it('shows buyer-cancelled unpaid orders as cancelled', () => {
    expect(
      resolveTransactionStatus({
        payment_status: 'failed',
        delivery_status: 'cancelled',
        refund_status: null
      }).label
    ).toBe('Dibatalkan')
  })

  it('shows the refund, not "Gagal", for an order cancelled after payment', () => {
    const status = resolveTransactionStatus({
      payment_status: 'failed',
      delivery_status: 'cancelled',
      refund_status: 'refunded'
    })

    expect(status.label).toBe('Dana Dikembalikan')
    expect(status.isFailure).toBe(false)
  })

  it('still shows "Gagal" for a payment that failed', () => {
    const status = resolveTransactionStatus({ payment_status: 'failed', refund_status: null })

    expect(status.label).toBe('Gagal')
    expect(status.isFailure).toBe(true)
  })
})
