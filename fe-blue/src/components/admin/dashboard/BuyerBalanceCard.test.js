import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const get = vi.fn()
vi.mock('@/plugins/axios', () => ({ axiosInstance: { get: (...args) => get(...args) } }))

import BuyerBalanceCard from './BuyerBalanceCard.vue'

const mountCard = async () => {
  const wrapper = mount(BuyerBalanceCard, { global: { stubs: { RouterLink: true } } })
  await flushPromises()
  return wrapper
}

const respond = (data) => get.mockResolvedValueOnce({ data: { data } })

const row = (id, type, amount, extra = {}) => ({
  id,
  type,
  amount,
  remarks: null,
  reference_code: null,
  created_at: '2026-10-07T10:00:00Z',
  ...extra
})

describe('BuyerBalanceCard', () => {
  beforeEach(() => get.mockReset())

  it('requests five rows and shows them with Indonesian labels and signs', async () => {
    respond({
      balance: 777000,
      histories: {
        data: [
          row('h1', 'payment', -23000),
          row('h2', 'refund', 800000, { reference_code: 'BLUE_1' }),
          row('h3', 'payment_returned', 5000),
          row('h4', 'refund', 0)
        ],
        meta: {}
      }
    })

    const wrapper = await mountCard()

    expect(get).toHaveBeenCalledWith('balance?per_page=5', expect.anything())
    expect(wrapper.find('[data-testid="balance-skeleton"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="balance"]').text()).toBe('Rp 777,000')

    const items = wrapper.findAll('li').map((li) => li.text())
    expect(items).toHaveLength(4)
    expect(items[0]).toContain('Dipakai belanja')
    expect(items[0]).toContain('−Rp 23,000')
    expect(items[1]).toContain('Refund')
    expect(items[1]).toContain('BLUE_1')
    expect(items[1]).toContain('+Rp 800,000')
    expect(items[2]).toContain('Dikembalikan')
    expect(items[2]).toContain('+Rp 5,000')
    expect(items[3]).toMatch(/(^|[^+−])Rp 0$/)
  })

  it('shows an empty state when there is no history', async () => {
    respond({ balance: 0, histories: { data: [], meta: {} } })

    const wrapper = await mountCard()

    expect(wrapper.get('[data-testid="balance"]').text()).toBe('Rp 0')
    expect(wrapper.text()).toContain('Belum ada riwayat saldo')
  })

  it('shows an error with a retry that refetches', async () => {
    get.mockRejectedValueOnce({ response: { status: 500, data: {} } })

    const wrapper = await mountCard()

    expect(wrapper.get('[role="alert"]').text()).toContain('Saldo Blukios gagal dimuat')

    respond({ balance: 1000, histories: { data: [], meta: {} } })
    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(get).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="balance"]').text()).toBe('Rp 1,000')
  })

  it('announces loading as a status', async () => {
    get.mockReturnValueOnce(new Promise(() => {}))

    const wrapper = mount(BuyerBalanceCard, { global: { stubs: { RouterLink: true } } })
    await wrapper.vm.$nextTick()

    expect(wrapper.get('[role="status"]').text()).toBe('Memuat Saldo Blukios')
  })
})
