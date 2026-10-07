import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

const post = vi.fn()
vi.mock('@/plugins/axios', () => ({ axiosInstance: { post: (...args) => post(...args) } }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))

import RefundPanel from './RefundPanel.vue'
import { useAuthStore } from '@/stores/auth'

const order = (extra = {}) => ({
  id: 'trx-1',
  refund_status: 'manual_required',
  refund_method: 'manual',
  refund_amount: 216000,
  refund_account: null,
  ...extra
})

const mountPanel = (props) =>
  mount(RefundPanel, {
    props,
    global: {
      stubs: {
        RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' }
      }
    }
  })

const balanceButton = (wrapper) =>
  wrapper.findAll('button').find((b) => b.text() === 'Kembalikan ke Saldo Blukios')

describe('RefundPanel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    post.mockReset()
    vi.stubGlobal(
      'confirm',
      vi.fn(() => true)
    )
  })

  it('tells the buyer the money went to Saldo Blukios and links the dashboard', () => {
    useAuthStore().user = { username: 'pembeli' }
    const wrapper = mountPanel({
      transaction: order({ refund_status: 'refunded', refund_method: 'balance' }),
      isBuyer: true
    })

    expect(wrapper.text()).toContain('Dana dikembalikan ke Saldo Blukios')
    expect(wrapper.get('a').attributes('data-to')).toContain('user.dashboard')
    expect(balanceButton(wrapper)).toBeUndefined()
  })

  it('shows the Saldo Blukios part of the payment, and no zero Midtrans amount', () => {
    const wrapper = mountPanel({
      transaction: order({
        refund_status: 'refunded',
        refund_method: 'balance',
        refund_amount: 0,
        balance_used: 216000
      }),
      isBuyer: true
    })

    expect(wrapper.get('[data-testid="refund-balance-used"]').text()).toMatch(/216[.,]000/)
    expect(wrapper.text()).not.toContain('Jumlah')
  })

  it('lets an admin move a manual refund to the balance after confirming', async () => {
    const updated = order({ refund_status: 'refunded', refund_method: 'balance' })
    let resolve
    post.mockReturnValueOnce(new Promise((r) => (resolve = r)))
    const wrapper = mountPanel({ transaction: order(), isAdmin: true })

    await balanceButton(wrapper).trigger('click')
    expect(post).toHaveBeenCalledWith('transaction/trx-1/refund-to-balance', {})
    expect(balanceButton(wrapper).attributes('disabled')).toBeDefined()

    resolve({ data: { data: updated } })
    await flushPromises()
    expect(wrapper.emitted('updated')[0]).toEqual([updated])
    expect(confirm.mock.calls[0][0]).not.toContain('BELUM ditransfer')
  })

  it('warns against paying twice when the buyer already gave a bank account', async () => {
    post.mockResolvedValueOnce({ data: { data: order() } })
    const account = { bank_name: 'BCA', account_number: '1234567890', account_name: 'Pembeli' }
    const wrapper = mountPanel({ transaction: order({ refund_account: account }), isAdmin: true })

    await balanceButton(wrapper).trigger('click')
    expect(confirm.mock.calls[0][0]).toContain('Pastikan dana BELUM ditransfer manual')
  })

  it('does nothing when the admin cancels the confirmation', async () => {
    confirm.mockReturnValueOnce(false)
    const wrapper = mountPanel({ transaction: order(), isAdmin: true })

    await balanceButton(wrapper).trigger('click')
    expect(post).not.toHaveBeenCalled()
  })

  it('shows the API error as an alert', async () => {
    post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { message: 'Pesanan ini tidak sedang menunggu refund manual' }
      }
    })
    const wrapper = mountPanel({ transaction: order(), isAdmin: true })

    await balanceButton(wrapper).trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('tidak sedang menunggu refund manual')
    expect(balanceButton(wrapper).attributes('disabled')).toBeUndefined()
  })
})
