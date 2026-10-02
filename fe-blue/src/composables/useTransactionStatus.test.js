import { describe, expect, it } from 'vitest'
import { resolveRefundStatus, resolveTransactionStatus } from './useTransactionStatus'

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
