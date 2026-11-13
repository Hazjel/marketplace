import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ProductEdit from './ProductEdit.vue'

const { post, get } = vi.hoisted(() => ({ post: vi.fn(), get: vi.fn() }))

vi.mock('@/plugins/axios', () => ({ axiosInstance: { post, get } }))
vi.mock('@/router', () => ({ default: { push: vi.fn() } }))
vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: '1' } }),
  useRouter: () => ({ push: vi.fn() })
}))

const stored = {
  id: 1,
  store_id: 1,
  name: 'Kaos',
  description: 'd',
  condition: 'new',
  price: 15000,
  compare_at_price: 20000,
  weight: 100,
  stock: 7,
  has_variants: false,
  variants: [],
  product_images: [{ id: 9, image: 'http://x/p.jpg', is_thumbnail: 1 }],
  product_category: { id: 2, parent_id: 1 }
}

// Hidden/derived parent compare_at must not be sent once variants are on.
describe('ProductEdit submit', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    post.mockReset().mockResolvedValue({ data: { message: 'ok' } })
    get.mockReset().mockImplementation(async (url) => ({
      data: { data: url.startsWith('product/') ? structuredClone(stored) : [] }
    }))
  })

  const submitWith = async (hasVariants) => {
    const wrapper = mount(ProductEdit, { global: { stubs: ['RouterLink'] } })
    await flushPromises()
    const { product } = wrapper.vm
    product.variants = [{ name: 'Merah', price: 99000, stock: 1, variant_attributes: {} }]
    product.has_variants = hasVariants
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    const body = post.mock.calls[0][1]
    wrapper.unmount()
    return body
  }

  it('sends the parent compare_at for a simple product', async () => {
    expect((await submitWith(false)).compare_at_price).toBe(20000)
  })

  it('sends compare_at_price null once variants are switched on', async () => {
    const body = await submitWith(true)

    expect(body.compare_at_price).toBeNull()
    expect(body.variants).toHaveLength(1)
  })
})
