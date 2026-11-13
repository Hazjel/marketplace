import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { axiosInstance } from '@/plugins/axios'
import { useProductStore } from './product'

vi.mock('@/plugins/axios', () => ({
  axiosInstance: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }
}))

// createProduct harus mengirim varian sebagai field array bracket
// (variants[0][name], ...). FormData.append biasa mengubah array/objek jadi
// "[object Object]", sehingga API (ProductStoreRequest: variants = array)
// menolak setiap produk bervarian dari form web.
describe('product store — createProduct multipart serialization', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    axiosInstance.post.mockResolvedValue({ data: { message: 'ok' } })
  })

  const image = new File(['x'], 'p.jpg', { type: 'image/jpeg' })

  it('serializes variants and variant_attributes as bracket fields', async () => {
    const result = await useProductStore().createProduct({
      name: 'Kaos',
      price: null,
      stock: null,
      has_variants: true,
      variants: [
        { name: 'Merah/S', price: 100000, stock: 4, variant_attributes: { Warna: 'Merah', Ukuran: 'S' } },
        { name: 'Biru/L', price: '150000', stock: '6', variant_attributes: {} }
      ],
      product_images: [{ image, is_thumbnail: 1 }]
    })

    expect(result.success).toBe(true)
    const [url, body] = axiosInstance.post.mock.calls[0]
    expect(url).toBe('product')
    expect(body).toBeInstanceOf(FormData)

    const fields = Object.fromEntries([...body.entries()].filter(([, v]) => typeof v === 'string'))
    expect(fields).toMatchObject({
      name: 'Kaos',
      has_variants: '1',
      'variants[0][name]': 'Merah/S',
      'variants[0][price]': '100000',
      'variants[0][stock]': '4',
      'variants[0][variant_attributes][Warna]': 'Merah',
      'variants[0][variant_attributes][Ukuran]': 'S',
      'variants[1][name]': 'Biru/L',
      'variants[1][price]': '150000',
      'product_images[0][is_thumbnail]': '1'
    })
    // null parent price/stock are skipped; nothing is stringified as an object
    expect(body.has('price')).toBe(false)
    expect(body.has('stock')).toBe(false)
    expect(body.has('variants')).toBe(false)
    expect([...body.values()]).not.toContain('[object Object]')
    expect(body.get('product_images[0][image]')).toBeInstanceOf(File)
  })

  it('omits an empty variants array for a simple product', async () => {
    await useProductStore().createProduct({
      name: 'Biasa',
      price: 5000,
      stock: 2,
      has_variants: false,
      variants: [],
      product_images: [{ image, is_thumbnail: 1 }]
    })

    const body = axiosInstance.post.mock.calls[0][1]
    expect(body.get('price')).toBe('5000')
    expect(body.get('stock')).toBe('2')
    expect(body.get('has_variants')).toBe('0')
    expect([...body.keys()].some((k) => k.startsWith('variants'))).toBe(false)
  })
})
