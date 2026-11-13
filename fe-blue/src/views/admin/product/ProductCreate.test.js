import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ProductCreate from './ProductCreate.vue'

const { post, get } = vi.hoisted(() => ({ post: vi.fn(), get: vi.fn() }))

vi.mock('@/plugins/axios', () => ({ axiosInstance: { post, get } }))
vi.mock('@/router', () => ({ default: { push: vi.fn() } }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))

// Menyalakan toggle varian, menambah baris, lalu mematikan toggle membiarkan
// baris itu di state. Submit tidak boleh ikut mengirimnya: API menganggap
// "ada variants" = produk bervarian, jadi produk biasa 15000 x 7 berubah
// jadi produk bervarian.
describe('ProductCreate submit', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    post.mockReset().mockResolvedValue({ data: { message: 'ok' } })
    get.mockReset().mockResolvedValue({ data: { data: [] } })
  })

  const submitWith = async (hasVariants) => {
    const wrapper = mount(ProductCreate, { global: { stubs: ['RouterLink'] } })
    await flushPromises()
    const { product, addVariant } = wrapper.vm
    product.has_variants = true
    addVariant()
    product.variants[0].name = 'Merah'
    product.variants[0].price = 99000
    product.variants[0].stock = 1
    product.has_variants = hasVariants
    product.price = 15000
    product.stock = 7
    product.product_images[0].image = new File(['x'], 'p.jpg', { type: 'image/jpeg' })
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    const body = post.mock.calls[0][1]
    wrapper.unmount()
    return body
  }

  it('does not send leftover variants once the toggle is switched off', async () => {
    const body = await submitWith(false)

    expect(body.get('price')).toBe('15000')
    expect(body.get('stock')).toBe('7')
    expect([...body.keys()].some((k) => k.startsWith('variants'))).toBe(false)
  })

  it('sends variants while the toggle is on', async () => {
    const body = await submitWith(true)

    expect(body.get('variants[0][name]')).toBe('Merah')
  })
})
