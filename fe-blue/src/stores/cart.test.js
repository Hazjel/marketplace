import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import Cookies from 'js-cookie'
import { axiosInstance } from '@/plugins/axios'
import { useCartStore } from './cart'

vi.mock('@/plugins/axios', () => ({
  axiosInstance: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }
}))

// Node 26's sendiri experimental global `localStorage` menutupi
// implementasi jsdom (didefinisikan non-configurable sebelum jsdom sempat
// memasangnya) -- Object.defineProperty di sini timpa paksa dengan
// polyfill in-memory sederhana khusus file test ini, tidak menyentuh
// setup global lain.
const memoryStorage = (() => {
  let store = {}
  return {
    getItem: (key) => (key in store ? store[key] : null),
    setItem: (key, value) => {
      store[key] = String(value)
    },
    removeItem: (key) => {
      delete store[key]
    },
    clear: () => {
      store = {}
    }
  }
})()
Object.defineProperty(globalThis, 'localStorage', { value: memoryStorage, configurable: true })
Object.defineProperty(window, 'localStorage', { value: memoryStorage, configurable: true })

// _addToLocalCart sebelumnya mencocokkan baris cart cuma lewat product.id,
// jadi menambahkan Varian A lalu Varian B dari produk yang SAMA ke-merge
// jadi satu baris (quantity dijumlahkan), tetap memakai variant_id/price
// dari yang pertama kali ditambahkan -- padahal keduanya harus jadi dua
// baris cart terpisah.
describe('cart store — variant-aware line matching', () => {
  beforeEach(() => {
    window.localStorage.clear()
    setActivePinia(createPinia())
  })

  const baseProduct = (overrides = {}) => ({
    id: 'product-1',
    store: { id: 'store-1', address_id: '1', name: 'Toko', logo: null },
    quantity: 1,
    ...overrides
  })

  it('keeps two different variants of the same product as separate cart lines', async () => {
    const cart = useCartStore()

    await cart.addToCart(baseProduct({ variant_id: 'variant-a', price: 100000 }))
    await cart.addToCart(baseProduct({ variant_id: 'variant-b', price: 150000 }))

    const store = cart.carts.find((s) => s.storeId === 'store-1')
    expect(store.products).toHaveLength(2)

    const lineA = store.products.find((p) => p.variant_id === 'variant-a')
    const lineB = store.products.find((p) => p.variant_id === 'variant-b')
    expect(lineA.quantity).toBe(1)
    expect(lineA.price).toBe(100000)
    expect(lineB.quantity).toBe(1)
    expect(lineB.price).toBe(150000)
  })

  it('still merges quantity when adding the same variant twice', async () => {
    const cart = useCartStore()

    await cart.addToCart(baseProduct({ variant_id: 'variant-a', price: 100000, quantity: 1 }))
    await cart.addToCart(baseProduct({ variant_id: 'variant-a', price: 100000, quantity: 2 }))

    const store = cart.carts.find((s) => s.storeId === 'store-1')
    expect(store.products).toHaveLength(1)
    expect(store.products[0].quantity).toBe(3)
  })

  it('merges non-variant products as before (regression)', async () => {
    const cart = useCartStore()

    await cart.addToCart(baseProduct({ price: 20000, quantity: 1 }))
    await cart.addToCart(baseProduct({ price: 20000, quantity: 1 }))

    const store = cart.carts.find((s) => s.storeId === 'store-1')
    expect(store.products).toHaveLength(1)
    expect(store.products[0].quantity).toBe(2)
  })
})

describe('cart store — server hydration resolves the selected variant, not the cheapest', () => {
  beforeEach(() => {
    window.localStorage.clear()
    setActivePinia(createPinia())
    Cookies.set('token', 'fake-token-for-test')
    axiosInstance.get.mockReset()
  })

  it('uses the selected variant price/stock from fetchCart(), not product.price (cheapest variant)', async () => {
    // item.product.price/stock adalah agregat (harga varian TERMURAH,
    // total stok lintas varian) -- bug aslinya: _applyServerCart() dulu
    // selalu memakai field itu langsung, jadi cart yang sudah benar
    // secara lokal kembali menampilkan harga varian termurah setiap kali
    // di-refresh dari server.
    axiosInstance.get.mockResolvedValueOnce({
      data: {
        data: [
          {
            store_id: 'store-1',
            store_name: 'Toko',
            store_logo: null,
            store_address_id: '1',
            items: [
              {
                id: 'cart-item-1',
                product_id: 'product-1',
                variant_id: 'variant-mahal',
                quantity: 2,
                note: null,
                product: {
                  name: 'Kaos Variasi',
                  price: 100000, // agregat -- harga varian TERMURAH
                  stock: 15,
                  weight: 200,
                  slug: 'kaos-variasi',
                  condition: 'new',
                  product_category: null,
                  product_images: [],
                  store: { id: 'store-1' },
                  variants: [
                    { id: 'variant-murah', name: 'Merah/S', price: 100000, stock: 10 },
                    { id: 'variant-mahal', name: 'Biru/L', price: 150000, stock: 5 }
                  ]
                }
              }
            ]
          }
        ]
      }
    })

    const cart = useCartStore()
    await cart.fetchCart()

    const store = cart.carts.find((s) => s.storeId === 'store-1')
    const line = store.products[0]
    expect(line.price).toBe(150000) // varian yang dibeli, BUKAN 100000
    expect(line.stock).toBe(5)
  })

  it('falls back to product.price/stock for non-variant items', async () => {
    axiosInstance.get.mockResolvedValueOnce({
      data: {
        data: [
          {
            store_id: 'store-1',
            store_name: 'Toko',
            store_logo: null,
            store_address_id: '1',
            items: [
              {
                id: 'cart-item-2',
                product_id: 'product-2',
                variant_id: null,
                quantity: 1,
                note: null,
                product: {
                  name: 'Produk Tanpa Varian',
                  price: 20000,
                  stock: 5,
                  weight: 100,
                  slug: 'produk-tanpa-varian',
                  condition: 'new',
                  product_category: null,
                  product_images: [],
                  store: { id: 'store-1' },
                  variants: []
                }
              }
            ]
          }
        ]
      }
    })

    const cart = useCartStore()
    await cart.fetchCart()

    const line = cart.carts.find((s) => s.storeId === 'store-1').products[0]
    expect(line.price).toBe(20000)
    expect(line.stock).toBe(5)
  })
})
