import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))
vi.mock('@/plugins/axios', () => ({ axiosInstance: { get: vi.fn(), post: vi.fn() } }))

import ProductCard from './ProductCard.vue'

const item = (extra = {}) => ({
  id: 'p1',
  slug: 'p1',
  name: 'Kabel USB-C',
  price: 100000,
  compare_at_price: null,
  discount_percent: null,
  stock: 3,
  total_sold: 0,
  store: { name: 'Toko A', is_verified: false },
  ...extra
})

const mountCard = (extra) =>
  mount(ProductCard, {
    props: { item: item(extra) },
    global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } }
  })

const badgeTexts = (wrapper) => wrapper.findAll('span.rounded.px-1\\.5').map((b) => b.text())

describe('ProductCard', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows the struck-through old price and the discount label', () => {
    const wrapper = mountCard({ compare_at_price: 150000, discount_percent: 33 })

    expect(wrapper.text()).toContain('Rp 100.000')
    expect(wrapper.find('s').text()).toBe('Rp 150.000')
    expect(wrapper.text()).toContain('-33%')
  })

  it('shows no old price or label without a discount', () => {
    const wrapper = mountCard()

    expect(wrapper.find('s').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('%')
  })

  it('shows Resmi then Terlaris, and nothing when neither applies', () => {
    expect(badgeTexts(mountCard({ is_best_seller: true, store: { name: 'T', is_verified: true } }))).toEqual([
      'Resmi',
      'Terlaris'
    ])
    expect(badgeTexts(mountCard({ is_best_seller: true }))).toEqual(['Terlaris'])
    expect(badgeTexts(mountCard({ store: { name: 'T', is_verified: true } }))).toEqual(['Resmi'])
    expect(badgeTexts(mountCard())).toEqual([])
  })
})
