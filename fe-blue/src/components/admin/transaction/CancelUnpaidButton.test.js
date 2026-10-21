import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import CancelUnpaidButton from './CancelUnpaidButton.vue'

const get = vi.fn()
const post = vi.fn()
vi.mock('@/plugins/axios', () => ({ axiosInstance: { get: (...args) => get(...args), post: (...args) => post(...args) } }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))

const order = { id: 'one', code: 'ORDER-ONE', buyer: { user: { id: 'buyer' } }, payment_status: 'unpaid', store: { name: 'Toko Satu' } }
const group = [order, { ...order, id: 'two', code: 'ORDER-TWO', store: { name: 'Toko Dua' } }]

describe('unpaid cancellation', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    const auth = useAuthStore()
    auth.user = { id: 'buyer', roles: ['buyer'] }
    auth.activeMode = 'buyer'
    get.mockReset()
    post.mockReset()
    get.mockResolvedValue({ data: { data: group } })
    post.mockResolvedValue({ data: { data: { payment_status: 'failed', cancelled_orders: group }, message: 'Pesanan dibatalkan' } })
    vi.stubGlobal('confirm', vi.fn(() => true))
  })

  it('confirms every order in the payment before cancelling and updates the caller', async () => {
    const wrapper = mount(CancelUnpaidButton, { props: { transaction: order } })
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(confirm.mock.calls[0][0]).toContain('ORDER-ONE')
    expect(confirm.mock.calls[0][0]).toContain('ORDER-TWO')
    expect(confirm.mock.calls[0][0]).toContain('Toko Dua')
    expect(get).toHaveBeenCalledWith('transaction/one/cancel-unpaid')
    expect(post).toHaveBeenCalledWith('transaction/one/cancel-unpaid', {})
    expect(wrapper.emitted('cancelled')[0][0].cancelled_orders).toHaveLength(2)
  })

  it('does not submit when the buyer declines the full-payment confirmation', async () => {
    confirm.mockReturnValue(false)
    const wrapper = mount(CancelUnpaidButton, { props: { transaction: order } })
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(post).not.toHaveBeenCalled()
    expect(wrapper.emitted('cancelled')).toBeUndefined()
  })

  it('does not cancel when preview fails and exposes an error', async () => {
    get.mockRejectedValue(new Error('offline'))
    const wrapper = mount(CancelUnpaidButton, { props: { transaction: order } })
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Gagal')
    expect(post).not.toHaveBeenCalled()
  })

  it('hides cancellation from paid orders and other buyers', () => {
    expect(mount(CancelUnpaidButton, { props: { transaction: { ...order, payment_status: 'paid' } } }).find('button').exists()).toBe(false)
    useAuthStore().user = { id: 'other' }
    expect(mount(CancelUnpaidButton, { props: { transaction: order } }).find('button').exists()).toBe(false)
  })

  it('disables retries during submission and hides the action after cancellation', async () => {
    let resolve
    post.mockReturnValueOnce(new Promise((r) => { resolve = r }))
    const wrapper = mount(CancelUnpaidButton, { props: { transaction: order } })
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    resolve({ data: { data: { payment_status: 'failed', cancelled_orders: group } } })
    await flushPromises()
    await wrapper.setProps({ transaction: { ...order, payment_status: 'failed', delivery_status: 'cancelled' } })
    expect(wrapper.find('button').exists()).toBe(false)
  })
})
